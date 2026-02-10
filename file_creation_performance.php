<?php

/**
 * Скрипт для тестирования скорости создания файлов как в мониторе производительности Битрикс
 *
 * Usage: php -f perfmon_files.php -- --test-dir="./../../upload"
 *        php -f perfmon_files.php -- --test-dir="./temp"
 *        php -f perfmon_files.php -- --test-dir="."
 */

/**
 * @param string $testDir
 * @return float|int
 */
function getFileCreationPerformance(string $testDir): float|int
{
    $res = [];
    $file_name = "$testDir/perfmon#i#.php";
    $content =
        '<?php $s=\''
        . str_repeat('x', 1024)
        . '\';?><?php /*'
        . str_repeat('y', 1024)
        . '*/?><?php $r=\''
        . str_repeat('z', 1024)
        . '\';?>';

    for ($j = 0; $j < 4; $j++) {
        $s1 = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $fn = str_replace('#i#', $i, $file_name);
        }
        $e1 = microtime(true);
        $N1 = $e1 - $s1;

        $s2 = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            //This is one op
            $fn = str_replace('#i#', $i, $file_name);
            $fh = fopen($fn, 'wb');
            fwrite($fh, $content);
            fclose($fh);
            include $fn;
            unlink($fn);
        }
        $e2 = microtime(true);
        $N2 = $e2 - $s2;

        if ($N2 > $N1) {
            $res[] = 100 / ($N2 - $N1);
        }
    }

    if (count($res)) {
        return array_sum($res) / doubleval(count($res));
    }

    return 0;
}

$options = getopt('', ['test-dir::']);

$testDir = $options['test-dir'] ?? __DIR__;
if (str_starts_with($testDir, '.')) {
    $testDir = __DIR__ . mb_strcut($testDir, 1);
}
$realTestDir = realpath($testDir);

if (!is_string($realTestDir)) {
    echo "Неправильный путь или директория не существует «{$testDir}»\n";
    exit(1);
}

echo "Тестирование создания файлов в директории «{$realTestDir}»\n\n";

for ($i = 1; $i <= 10; $i++) {
    echo "Замер №$i: " . number_format(getFileCreationPerformance($realTestDir), 1, '.', ' ') . PHP_EOL;
    sleep(1);
}

echo PHP_EOL;
exit(0);
