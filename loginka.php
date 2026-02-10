<?php

/**
 * Одноразовая экстренная авторизация в админку Битрикс
 *
 * Скрипт авторизует под первым активным незаблокированным админа (группа 1)
 * и редиректит в /bitrix/admin/. После любого обращения (успешного или нет) удаляет сам себя.
 *
 * Если не заблокированного админа нет, скрипт пробует авторизовать под заблокированным админом
 *
 * Перед использованием задайте константы:
 * - AUTO_AUTHORIZE_GET_PARAM_NAME — имя GET-параметра (секретный ключ в URL),
 * - AUTO_AUTHORIZE_SECRET_KEY — значение GET-параметра,
 * - AUTO_AUTHORIZE_ACCESSIBLE_UNTIL — UNIX-timestamp, после которого скрипт перестанет работать (0 — без ограничения).
 *
 * Пример вызова: https://example.com/loginka.php?some_secret_key=test+password
 */

const AUTO_AUTHORIZE_GET_PARAM_NAME = 'some_secret_key';
const AUTO_AUTHORIZE_SECRET_KEY = 'test password';
const AUTO_AUTHORIZE_ACCESSIBLE_UNTIL = 0;

$isValid = isset($_GET)
    && is_array($_GET)
    && array_key_exists(AUTO_AUTHORIZE_GET_PARAM_NAME, $_GET)
    && trim($_GET[AUTO_AUTHORIZE_SECRET_KEY]) === AUTO_AUTHORIZE_SECRET_KEY
    && (AUTO_AUTHORIZE_ACCESSIBLE_UNTIL === 0 || time() <= AUTO_AUTHORIZE_ACCESSIBLE_UNTIL);

if (!$isValid) {
    unlink(__FILE__);
    header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request', true, 400);
    die();
}

try {
    define('NOT_CHECK_PERMISSIONS', true);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

    $by = 'id';
    $order = 'asc';

    $adminUserResult = \CUser::GetList(
        $by,
        $order,
        ['ACTIVE' => 'Y', 'GROUPS_ID' => ['1']],
        [
            'FIELDS' => ['ID', 'BLOCKED']
        ]
    );

    $blockedAdminUser = false;
    while ($adminUser = $adminUserResult->Fetch()) {
        if ($adminUser['BLOCKED'] !== 'Y') {
            break;
        } else {
            $blockedAdminUser = $adminUser;
        }
    }

    if (!$adminUser) {
        $adminUser = $blockedAdminUser;
    }

    if (!$adminUser) {
        unlink(__FILE__);
        header('HTTP/1.0 404 Not Found', true, 404);
        die();
    }

    /** @global \CUser $USER */
    $authResult = $USER->Authorize((int)$adminUser['ID'], false, false, null, false);
    unlink(__FILE__);

    if (!$authResult) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
    } else {
        LocalRedirect('/bitrix/admin/');
    }
    die(); // На всякий случай
} catch (\Throwable $throwable) {
    unlink(__FILE__);
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
    die();
}
