<?php

declare(strict_types=1);

namespace Kekke\Mononoke;

use Kekke\Mononoke\Exceptions\MononokeException;

// ANSI color codes
const RESET = "\e[0m";
const BOLD = "\e[1m";
const DIM = "\e[2m";
const GREEN = "\e[32m";
const CYAN = "\e[36m";
const YELLOW = "\e[33m";
const MAGENTA = "\e[35m";

class Framework
{
    private static string $version = "0.1.2";

    public static function run(string $file): void
    {
        require_once $file;

        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Service::class)) {
                $service = new $class();
                self::displayInfo();
                $service->run();
                return;
            }
        }

        throw new MononokeException("No class extending MononokeService was found in: $file");
    }

    public static function displayInfo(): void
    {
        echo <<<ASCII
\e[1;35m
 __  __   ___   _   _   ___   _   _   ___   _  __ _____ 
|  \/  | / _ \ | \ | | / _ \ | \ | | / _ \ | |/ /| ____|
| |\/| || | | ||  \| || | | ||  \| || | | || ' / |  _|  
| |  | || |_| || |\  || |_| || |\  || |_| || . \ | |___ 
|_|  |_| \___/ |_| \_| \___/ |_| \_| \___/ |_|\_\|_____|
\e[0m
ASCII;

        echo PHP_EOL;
        echo BOLD . CYAN . "Mononoke Runtime Info" . RESET . PHP_EOL;
        echo BOLD . str_repeat('-', 60) . RESET . PHP_EOL;

        $serviceFile = $_SERVER['argv'][1] ?? 'N/A';
        $os = php_uname();
        $phpVersion = PHP_VERSION;
        $mononokeVersion = self::$version;

        printf("%-20s: %s\n", GREEN . "Service File" . RESET, $serviceFile);
        printf("%-20s: %s\n", GREEN . "Operating System" . RESET, $os);
        printf("%-20s: %s\n", GREEN . "PHP Version" . RESET, $phpVersion);
        printf("%-20s: %s\n", GREEN . "Mononoke Version" . RESET, YELLOW . $mononokeVersion . RESET);

        echo PHP_EOL;
    }
}
