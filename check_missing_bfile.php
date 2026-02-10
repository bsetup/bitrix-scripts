<?php
declare(strict_types=1);

/**
 * Поиск записей b_file с отсутствующими файлами на диске
 *
 * CLI-скрипт проверяет все записи таблицы b_file (кроме облачных хранилищ)
 * и определяет, существует ли соответствующий физический файл в /upload/.
 *
 * Результат — CSV-файл со списком отсутствующих файлов (ID, MODULE_ID, TIMESTAMP_X, FILE_NAME, FILE_PATH).
 * Прогресс выводится в STDOUT постранично (по 100 000 записей).
 */

$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS',true);
define('BX_NO_ACCELERATOR_RESET', true);
define('BX_CRONTAB', true);
define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', 'Y');
define('DisableEventsCheck', true);
define('NO_AGENT_CHECK', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

@set_time_limit(0);
@session_destroy();
while (ob_get_level() !== 0) {
    ob_end_flush();
}

$step = 100000;
$page = 1;

$foundCount = 0;
$notFoundCount = 0;

$countTotal = (int)\Bitrix\Main\FileTable::query()->queryCountTotal();
$finalPage = ceil($countTotal / $step);

echo "Стартуем...\n";

$formatBytes = function (int $bytes) : string {
    return ($bytes / 2 ** 20) . 'Mb';
};

$csvLogFilename = __DIR__ . '/check_bfile_missing_files.' . date('d_m_Y_H_i_s') . '.csv';
$csvLogResource = fopen($csvLogFilename, 'w');
fputcsv($csvLogResource, [
    'ID',
    'MODULE_ID',
    'TIMESTAMP_X',
    'FILE_NAME',
    'FILE_PATH',
]);

while ($page < $finalPage) {
    fwrite(STDOUT, sprintf(
        "\rPage %s/%s | Found %s | Missed %s",
        $page,
        $finalPage,
        $foundCount,
        $notFoundCount,
    ));

    $result  = \Bitrix\Main\FileTable::query()
        ->setSelect(['ID', 'SUBDIR', 'FILE_NAME', 'TIMESTAMP_X', 'MODULE_ID', 'ORIGINAL_NAME'])
        ->whereNull('HANDLER_ID') // пропускаем файлы в облачных хранилищах
        ->setOrder(['ID' => 'ASC'])
        ->setLimit($step)
        ->setOffset($step * ($page - 1))
        ->exec();

    while ($file = $result->fetch()) {
        $filePath = "/upload/{$file['SUBDIR']}/{$file['FILE_NAME']}";

        if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $filePath)) {
            $notFoundCount += 1;
            fputcsv($csvLogResource, [
                $file['ID'],
                $file['MODULE_ID'],
                $file['TIMESTAMP_X'],
                $file['FILE_NAME'],
                $filePath,
            ]);
        } else {
            $foundCount += 1;
        }
    }

    $page++;
}

fwrite(STDOUT, sprintf(
    "\rPage %s/%s | Found %s | Missed %s",
    $page,
    $finalPage,
    $foundCount,
    $notFoundCount,
));

fwrite(STDOUT, "\nВсего отсутствует файлов на диске: $notFoundCount\n");
fwrite(STDOUT, "Найдено файлов: $foundCount\n");

fclose($csvLogResource);
if ($notFoundCount === 0) {
    unlink($csvLogFilename);
} else {
    fwrite(STDOUT, "Список отсутствующих файлов записан в $csvLogFilename\n");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
