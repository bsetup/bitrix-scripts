<?php

/**
 * Отладка отправки писем
 *
 * Скрипт выполняет следующие действия:
 * 1. Выводит текущую конфигурацию почты — email_from главного модуля, email сайтов,
 *    настройки smtp из .settings.php, константы ONLY_EMAIL и ERROR_EMAIL.
 * 2. Выводит зарегистрированные обработчики событий OnBeforeEventAdd, OnBeforeMailSend и OnBeforePhpMail.
 * 3. Регистрирует логирующие обработчики на эти же события, чтобы отследить прохождение письма через цепочку отправки.
 * 4. Отправляет тестовое письмо двумя способами:
 *    - Event::send (отложенная отправка через b_event),
 *    - Event::sendImmediate (немедленная отправка).
 */

// Адрес, на который будет отправлено тестовое письмо
$emailTo = 'user@yourdo.main';

// Настройки тестового письма: сайт, событие и значения полей
$emailSiteId = \CSite::GetDefSite();
$emailEvent = 'USER_INFO';
$emailEventFields = [
    'EMAIL_TO' => $emailTo,
    'USER_ID' => 1,
    'MESSAGE' => 'https://dev.1c-bitrix.ru/',
];

$eventManager = \Bitrix\Main\EventManager::getInstance();
function b_pretty_dump($value): void
{
    var_export($value);
    echo "\n\n";
}

// region Вывод настроек

$defaultEmailFrom = \Bitrix\Main\Config\Option::get('main', 'email_from');
echo "Настройка главного модуля \"Отправитель по умолчанию\" равна \"$defaultEmailFrom\"\n";

$sites = \Bitrix\Main\SiteTable::query()
    ->setSelect(['*'])
    ->exec()
    ->fetchCollection();

foreach ($sites as $site) {
    echo "Настройка сайта {$site->getLid()} \"E-Mail адрес по умолчанию\" равна \"{$site->getEmail()}\"\n";
}

echo "\n";

$smtpConfiguration = \Bitrix\Main\Config\Configuration::getValue('smtp');
if (is_array($smtpConfiguration)) {
    echo "В .settings.php задана smtp конфигурация\n";
    b_pretty_dump($smtpConfiguration);
} else {
    echo "В .settings.php не задана smtp конфигурация\n\n";
}

if (defined('ONLY_EMAIL')) {
    echo "Объявлена константа ONLY_EMAIL, функция \\Bitrix\\Main\\Mail\\Mail::send будет отправлять письма только на адрес " . ONLY_EMAIL . "\n\n";
}

if (defined('ERROR_EMAIL') && ERROR_EMAIL <> '') {
    $errorTo = ERROR_EMAIL;
    $errorFrom = (defined('ERROR_EMAIL_FROM') && ERROR_EMAIL_FROM <> '' ? ERROR_EMAIL_FROM : 'error@bitrix.ru');
    echo "Объявлена константа ERROR_EMAIL, функция \\CAllDatabase::Query будет отправлять письма с sql ошибками от $errorFrom на адрес $errorTo\n\n";
}

// endregion

// region Вывод зарегистрированных событий

echo "Зарегистрированные обработчики событий main:OnBeforeEventAdd:\n";
b_pretty_dump($eventManager->findEventHandlers(
    'main',
    'OnBeforeEventAdd'
));

echo "Зарегистрированные обработчики событий main:OnBeforeMailSend:\n";
b_pretty_dump($eventManager->findEventHandlers(
    'main',
    'OnBeforeMailSend'
));

echo "Зарегистрированные обработчики событий main:OnBeforePhpMail:\n";
b_pretty_dump($eventManager->findEventHandlers(
    'main',
    'OnBeforePhpMail'
));

// endregion

// region Логирование событий ядра

$eventManager->addEventHandler(
    'main',
    'OnBeforeEventAdd',
    function () {
        echo "Сработало событие OnBeforeEventAdd\n";
        b_pretty_dump(func_get_args());
    }
);

$eventManager->addEventHandler(
    'main',
    'OnBeforeMailSend',
    function (\Bitrix\Main\Event $event) {
        echo "Сработало событие OnBeforeMailSend\n";
        $params = $event->getParameters()[0];
        if (isset($params['BODY'])) {
            $params['BODY'] = mb_substr($params['BODY'], 0, 20) . '...';
        }
        b_pretty_dump($params);
    }
);

$eventManager->addEventHandler(
    'main',
    'OnBeforePhpMail',
    function (\Bitrix\Main\Event $event) {
        echo "Сработало событие OnBeforePhpMail в функции bxmail\n";
        $params = (array)$event->getParameters()['arguments'];
        if (isset($params['message'])) {
            $params['message'] = mb_substr($params['message'], 0, 20) . '...';
        }
        b_pretty_dump($params);
    }
);

// endregion

// region Отложенная отправка письма через b_event

echo "Попытка отправить письмо функцией \\Bitrix\\Main\\Mail\\Event::send\n\n";

$sendResult = \Bitrix\Main\Mail\Event::send([
    'EVENT_NAME' => $emailEvent,
    'LID' => $emailSiteId,
    'C_FIELDS' => $emailEventFields,
]);

echo "Результат отправки письма функцией \\Bitrix\\Main\\Mail\\Event::send\n";
if ($sendResult->isSuccess()) {
    echo "Успешно\n\n";
} else {
    echo "Ошибка: " . implode('; ', $sendResult->getErrorMessages()) . "\n\n";
}

// endregion

// region Незамедлительная отправка письма

echo "Попытка отправить письмо функцией \\Bitrix\\Main\\Mail\\Event::sendImmediate\n\n";

$sendResult = \Bitrix\Main\Mail\Event::sendImmediate([
    'EVENT_NAME' => $emailEvent,
    'LID' => $emailSiteId,
    'C_FIELDS' => $emailEventFields,
]);

echo "Результат отправки письма функцией \\Bitrix\\Main\\Mail\\Event::sendImmediate\n";
switch ($sendResult) {
    case \Bitrix\Main\Mail\Event::SEND_RESULT_NONE:
        echo "Письмо не отправлено\n";
        break;
    case \Bitrix\Main\Mail\Event::SEND_RESULT_SUCCESS:
        echo "Письмо успешно отправлено\n";
        break;
    case \Bitrix\Main\Mail\Event::SEND_RESULT_ERROR:
        echo "Ошибка отправки письма\n";
        break;
    case \Bitrix\Main\Mail\Event::SEND_RESULT_PARTLY:
        echo "Часть получателей получили письмо, часть - нет\n";
        break;
    case \Bitrix\Main\Mail\Event::SEND_RESULT_TEMPLATE_NOT_FOUND:
        echo "Шаблон письма не найден\n";
        break;
}

// endregion
