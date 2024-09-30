<?php
include_once 'config.php';

if(CFG_LOGGING){
    error_reporting(-1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/php-error.txt');
}

// определяем кодировку
header('Content-type: text/html; charset=utf-8');
// Создаем объект бота
$bot = new Bot();
// Обрабатываем пришедшие данные
$bot->init('php://input');

/**
 * Class Bot ifeedbackbot
 */
class Bot
{
    // <bot_token> - созданный токен для нашего бота от @BotFather
    private $botToken = CFG_TOKEN; // 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
    // наш уникальный id в Telegramm - можно воспользоваться @userinfobot при старте он вам его покажет
    private $adminId = CFG_ADMIN;
    // адрес для запросов к API Telegram
    private $apiUrl = CFG_API;
    // Приветствие для админа при старте
    private $helloAdmin = "Приветствую тебя Создатель. 🙏\nНачинаем ждать сообщений от пользователей.";
    // Приветствие для пользователя при старте
    private $helloUser = "Приветствую Вас {username}. 👋\nМы ждем вашего сообщения.\n------\nСпасибо.";
    // Сообщение в случае если админ напишет боту
    private $answerAdmin = "Выберите в контекстном меню функцию Ответить/Reply в сообщении, на которое хотите ответить\n ";

    private $answerDonate = "Лучшая поддержка это распространение стикеров повсюду ✨\n\nОднако, если Вы всё-таки хотите поддержать меня материально, то нажмите кнопку ниже, чтобы увидеть реквизиты <3";

    private $randomCatURL = CFG_CAT;

    private $stickerPacks = CFG_STICKERS;


    /** Обрабатываем сообщение
     * @param $data
     */
    public function init($data)
    {
        // создаем массив из пришедших данных от API Telegram
        $arrData = $this->getData($data);
        // Определяем id пользователя
        $chat_id = $arrData['message']['from']['id'];
        // проверяем кто написал: пользователь или админ
        $is_admin = $this->isAdmin($chat_id);
        // обработка нажатий inline кнопок
        if (isset($arrData['callback_query'])) {
            switch($arrData['callback_query']['data']){
                case "showSupport": $this->displaySupport($arrData);
                break;
                case "showCat": $this->displayCat($arrData);
                break;
                case "showStickers": $this->displayStickers($arrData);
                break;
                case "showPaymentMethods": $this->displaySupport($arrData, NULL, 1);
                break;
                case "hidePaymentMethods": $this->displaySupport($arrData, NULL, 2);
                break;
                case "addToBlacklist": $this->addToBlacklist($arrData);
                break;
                case "exitChat": $this->exitChat($arrData);
                break;
                case "sendSupportToChat": 
                    $exploded = explode(":", $arrData['callback_query']['message']['text']);
                    if($exploded[0] == "ID") {
                        $this->displaySupport($arrData, $exploded[1]);
                    }
                    $this->requestToTelegram(array("text" => "Сообщение отправлено", "show_alert" => false, 'callback_query_id' => $arrData['callback_query']['id']), null, "answerCallbackQuery");
                break;
            }
        }
        if ($this->isUserInFile(CFG_BLACKLIST, $chat_id)) {
            $this->requestToTelegram(array("sticker" => "CAACAgIAAxkBAAKBYGb5hdBzu8f6hjBEga_RIxll0FZ3AAJpRwACIfohSbDLv4aa4M-VNgQ"), $chat_id, "sendSticker");
            exit();
        }
        // если это Старт
        if($this->isStartBot($arrData)) {
            // Определяем кто написал
            $chat_id = $is_admin ? $this->adminId : $chat_id;
            // Выводим приветственное слово
            $hello = $is_admin ? $this->helloAdmin : $this->setTextHello($this->helloUser, $arrData);
            // Отправляем сообщение

            $this->requestToTelegram(array("text" => $hello), $chat_id, "sendMessage");
            $this->requestToTelegram(array("sticker" => "CAACAgIAAxkBAAIB5WWj6MjPeqIZuH8CaALxD8G9KgRgAAKWOwACo33oSNC_B-9StuiSNAQ"), $chat_id, "sendSticker");
            // формируем json для inline кнопок под сообщением
            $keyboard = [ "inline_keyboard" =>
                [ /* ряд кнопок */
                    [
                        [
                            "text" => "Стикерпаки",
                            "callback_data" => "showStickers"
                        ],
                        [
                            "text" => "Рандомный котик",
                            "callback_data" => "showCat"
                        ]
                    ],
                    [
                        [
                            "text" => "Поддержать",
                            "callback_data" => "showSupport"
                        ]
                    ]
                ]
            ];
            $keyboard_json = json_encode($keyboard);
            $this->requestToTelegram(array("text" => "Вы можете воспользоваться кнопками ниже или написать сообщение которое я обязательно прочитаю 👽", "reply_markup" => $keyboard_json), $chat_id, "sendMessage");
        } elseif($this->isSupportBot($arrData)){
            $this->displaySupport($arrData, $chat_id);
        } elseif($this->isStickersBot($arrData)){
            $this->displayStickers($arrData, $chat_id);
        } elseif($this->isCatBot($arrData)){
            $this->displayCat($arrData, $chat_id);
        } else {
            // Если это не старт не кот и не саппорт
            if($is_admin)  {
                if($this->isReply($arrData)) {
                    // если ответ самому себе
                    if($this->isAdmin($arrData['message']['reply_to_message']['from']['id'])) {
                        $this->requestToTelegram(array("text" => "Вы ответили сами себе. 🤪"), $this->adminId, "sendMessage");
                    } elseif($arrData['message']['reply_to_message']['forward_origin']['type'] == "hidden_user") {
                        // если ответ cкрытому юзеру
                        $this->requestToTelegram(array("text" => "Пользователь скрыл свой профиль. 🤡\nОтветьте на сообщение с его ID."), $this->adminId, "sendMessage");
                    } elseif($this->isBot($arrData)) {
                        $exploded = explode(":", $arrData['message']['reply_to_message']['text']);
                        if($exploded[0] == "ID") {
                            // если ответ анонимному юзеру ID которого прислал бот
                            $this->getTypeCommand($arrData, explode(":", $exploded[1]));
                            //$this->requestToTelegram(array("text" => $arrData['message']['text']), $exploded[1], "sendMessage");
                        } else {
                            // если ответ боту
                            $this->requestToTelegram(array("text" => "Вы ответили Боту. 🤖"), $this->adminId, "sendMessage");
                        }
                    } else {
                        // все нормально отправляем на обработку
                        $this->getTypeCommand($arrData);
                    }
                } else {
                    // нажать кнопку ответить
                    $this->requestToTelegram(array("text" => $this->answerAdmin), $this->adminId, "sendMessage");
                }
            } else {
                // Если это написал пользователь то перенаправляем админу
                $dataSend = array(
                    'from_chat_id' => $arrData['message']['from']['id'],
                    'message_id' => $arrData['message']['message_id'],
                );
                $this->requestToTelegram($dataSend, $this->adminId, "forwardMessage");
                    $keyboard = [ "inline_keyboard" =>
                        [
                            [
                                [
                                    "text" => "Бан",
                                    "callback_data" => "addToBlacklist"
                                ],
                                [
                                    "text" => "Выйти",
                                    "callback_data" => "exitChat"
                                ],
                                [
                                    "text" => "Показать донат",
                                    "callback_data" => "sendSupportToChat"
                                ]
                            ]
                        ]
                    ];
                    $keyboard_json = json_encode($keyboard);

                    if(isset($arrData['message']['from']['id'])){
                        $this->requestToTelegram(array("text" => "ID:".$arrData['message']['from']['id'], "reply_markup" => $keyboard_json), $this->adminId, "sendMessage");
                    }
                /*if (!isset($arrData['callback_query'])){
                    $this->requestToTelegram(array("text" => "ID:".$arrData['message']['from']['id']), $this->adminId, "sendMessage");
                }*/
            }
        }
    }

    /** Отображаем инфу по поддержке
     * @param $data
     * @param $chat_id is empty if called in callback_query
     */
    private function displaySupport($arrData, $chat_id = NULL, $methodsShow = 0) {
        $chat_id = $chat_id != NULL ? $chat_id : $arrData['callback_query']['message']['chat']['id'];

        $tempEditedMessage = "";
        foreach(CFG_MONEY as $method=>$code) {
            $tempMethod = "";
            switch($method){
                case "donationalerts": $tempMethod = "DonationAlerts";
                break;
                case "btc": $tempMethod = "Bitcoin";
                break;
                case "ton": $tempMethod = "Ton";
                break;
                case "usdt": $tempMethod = "USDT";
                break;
                case "visa": $tempMethod = "Visa";
                break;
                case "mastercard": $tempMethod = "MasterCard";
                break;
                case "mir": $tempMethod = "Мир";
                break;
            }
            $tempEditedMessage .= "**" . $tempMethod . "**:\n" . "`" . $code . "`\n\n";
        }

            if($methodsShow == 0) {

            $this->requestToTelegram(array("sticker" => "CAACAgIAAxkBAAIDemWkRIcsYuRYj_G6VAWU1WUP3bBgAAKNOQACyJupSA8_Z3cM36LFNAQ"), $chat_id, "sendSticker");
            $this->requestToTelegram(array("text" => $this->answerDonate, "reply_markup" => json_encode([ "inline_keyboard" =>
                    [ /* ряд кнопок */
                        [
                            [
                                "text" => "МИР / TON Coin / Bitcoin",
                                "callback_data" => "showPaymentMethods"
                            ]
                        ]
                    ]
                ])), $chat_id, "sendMessage");

            } 

            if($methodsShow == 1) {

            $this->requestToTelegram(array("text" => $tempEditedMessage, "parse_mode"=>'Markdown', "reply_markup" => json_encode([ "inline_keyboard" =>
                    [
                        [
                            [
                                "text" => "Скрыть / Hide",
                                "callback_data" => "hidePaymentMethods"
                            ]
                        ]
                    ]
                ]), "message_id"=>$arrData['callback_query']['message']['message_id']), $chat_id, "editMessageText");
            
            }

            if($methodsShow == 2) {

            $this->requestToTelegram(array("text" => $this->answerDonate, "reply_markup" => json_encode([ "inline_keyboard" =>
                    [ /* ряд кнопок */
                        [
                            [
                                "text" => "МИР / TON Coin / Bitcoin",
                                "callback_data" => "showPaymentMethods"
                            ]
                        ]
                    ]
                ]), "message_id"=>$arrData['callback_query']['message']['message_id']), $chat_id, "editMessageText");
            
            }

        
    }

    /** Отображаем стикерпаки
     * @param $data
     * @param $chat_id is empty if called in callback_query
     */
    private function displayStickers($arrData, $chat_id = NULL) {
        $chat_id = $chat_id != NULL ? $chat_id : $arrData['callback_query']['from']['id'];

		$list = array();

        foreach($this->stickerPacks as $pack) {
        	array_push($list, array(["text"=>$pack['text'],'url' => $pack['link']]));
        }

        $replyMarkup = array("inline_keyboard" => $list);

        $encodedKeyboard = json_encode($replyMarkup);

        $this->requestToTelegram(array("text" => "Вот все стикерпаки которые доступны на данный момент. 😸\nВсегда открыт к предложениям и идеям, не стесняйтесь писать :3", "reply_markup" => $encodedKeyboard), $chat_id, "sendMessage");

        // $this->stickerPacks
        foreach($this->stickerPacks as $pack) {
        	$this->requestToTelegram(array("sticker" => $pack['sticker']), $chat_id, "sendSticker");
        }
    }

    private function addToBlacklist($data) {
        $exploded = explode(":", $data['callback_query']['message']['text']);
        if($exploded[0] == "ID") {
            $this->requestToTelegram(array("text" => $this->toggleUserInFile(CFG_BLACKLIST, $exploded[1]), "show_alert" => false, 'callback_query_id' => $data['callback_query']['id']), null, "answerCallbackQuery");
        }
    }

    private function exitChat($data) {
        $this->requestToTelegram(array("text" => "Функция пока не реализована", "show_alert" => false, 'callback_query_id' => $data['callback_query']['id']), null, "answerCallbackQuery");
    }

    /** Отображаем рандомного кота
     * @param $data
     * @param $chat_id is empty if called in callback_query
     */
    private function displayCat($arrData, $chat_id = NULL) {
        $chat_id = $chat_id != NULL ? $chat_id : $arrData['callback_query']['from']['id'];
        $url = $this->randomCatURL;
        $json = file_get_contents($url);
        $json_data = json_decode($json, true);
        $this->requestToTelegram(array("text" => '<a href="'.$json_data[0]["url"].'">🐈</a>', "parse_mode" => "HTML"), $chat_id, "sendMessage");
    }

    /** Проверяем не отвечаем ли мы боту
     * @param $data
     * @return bool
     */
    private function isBot($data) {
        return ($data['message']['reply_to_message']['from']['is_bot'] == 1
            && !array_key_exists('forward_from', $data['message']['reply_to_message']));
    }

    /** проверяем на Reply
     * @param $data
     * @return bool
     */
    private function isReply($data) {
        return array_key_exists('reply_to_message', $data['message']) ? true : false;
    }

    /** Подставляем имя пользователя
     * @param $text
     * @param $data
     * @return mixed
     */
    private function setTextHello($text, $data) {
        // узнаем имя и фамилию отправителя
        $username = $this->getNameUser($data);
        // подменяем {username} на Имя и Фамилию
        return str_replace("{username}", $username, $text);
    }

    /** Получаем имя и фамилию пользователя
     * @param $data
     * @return string
     */
    private function getNameUser($data) {
        return $data['message']['chat']['first_name'] . " " . $data['message']['chat']['last_name'];
    }

    /** Определяем роль отправителя
     * @param $id
     * @return bool
     */
    private function isAdmin($id)
    {
        return ($id == $this->adminId) ? true : false;
    }

    /** Проверяем на команду /start
     * @param $data
     * @return bool
     */
    private function isStartBot($data) {
        return ($data['message']['text'] == "/start") ? true : false;
    }

    /** Проверяем на команду /support
     * @param $data
     * @return bool
     */
    private function isSupportBot($data) {
        return ($data['message']['text'] == "/support") ? true : false;
    }

    /** Проверяем на команду /cat
     * @param $data
     * @return bool
     */
    private function isCatBot($data) {
        return ($data['message']['text'] == "/cat") ? true : false;
    }
    private function isStickersBot($data) {
        return ($data['message']['text'] == "/stickers") ? true : false;
    }

    /** Определяем тип сообщения и передаем для отправки
     * @param $data
     */
    private function getTypeCommand($data, $customID = NULL)
    {
        // определяем id пользователя для уведомления
        $chat_id = $data['message']['reply_to_message']['forward_from']['id'];

        if (isset($customID)) {
            $chat_id = $customID[0];
        }

        // если текст
        if (array_key_exists('text', $data['message'])) {
            // готовим данные
            $dataSend = array(
                'text' => $data['message']['text'],
            );
            // отправляем - передаем нужный метод
            $this->requestToTelegram($dataSend, $chat_id, "sendMessage");
        } elseif (array_key_exists('sticker', $data['message'])) {
            $dataSend = array(
                'sticker' => $data['message']['sticker']['file_id'],
            );
            $this->requestToTelegram($dataSend, $chat_id, "sendSticker");
        } elseif (array_key_exists('document', $data['message'])) {
            $dataSend = array(
                'document' => $data['message']['document']['file_id'],
                'caption' => $data['message']['caption'],
            );
            $this->requestToTelegram($dataSend, $chat_id, "sendDocument");
        } elseif (array_key_exists('photo', $data['message'])) {
            // картинки Телеграм ресайзит и предлагает разные размеры, мы берем самый последний вариант
            // так как он самый большой - то есть оригинал
            $img_num = count($data['message']['photo']) - 1;
            $dataSend = array(
                'photo' => $data['message']['photo'][$img_num]['file_id'],
                'caption' => $data['message']['caption'],
            );
            $this->requestToTelegram($dataSend, $chat_id, "sendPhoto");
        } elseif (array_key_exists('video', $data['message'])) {
            $dataSend = array(
                'video' => $data['message']['video']['file_id'],
                'caption' => $data['message']['caption'],
            );
            $this->requestToTelegram($dataSend, $chat_id, "sendVideo");
        } else {
            $this->requestToTelegram(array("text" => "Тип передаваемого сообщения не поддерживается"), $chat_id, "sendMessage");
        }
    }

    /**
    * Парсим что приходит преобразуем в массив
    * @param $data
    * @return mixed
    */
    private function getData($data)
    {
        if(CFG_LOGGING){
            $this->setFileLog($data);
        }
        return json_decode(file_get_contents($data), TRUE);
    }

    private function setFileLog($data) {
        $data = json_decode(file_get_contents($data), TRUE);
        $fh = fopen(CFG_LOGS, 'a') or die('can\'t open file');
        ((is_array($data)) || (is_object($data))) ? fwrite($fh, print_r($data, TRUE)."\n") : fwrite($fh, $data . "\n");
        fclose($fh);
    }

    /** Отправляем запрос в Телеграмм
     * @param $data
     * @param string $type
     * @return mixed
     */
    private function requestToTelegram($data, $chat_id, $type)
    {
        $result = null;
        $data['chat_id'] = $chat_id;
        

        if (is_array($data)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $this->botToken . '/' . $type);
            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $result = curl_exec($ch);
            curl_close($ch);
        }
        return $result;
    }

    // Функция для проверки, есть ли пользователь в файле
    function isUserInFile($filePath, $userId) {
        if (!file_exists($filePath)) {
            return false; // Если файла нет, пользователя точно нет
        }

        // Открываем файл для чтения
        $file = fopen($filePath, 'r');
        if ($file) {
            while (($line = fgets($file)) !== false) {
                $line = trim($line); // Убираем возможные пробелы и переносы строк
                if ($line == $userId) {
                    fclose($file); // Не забываем закрыть файл
                    return true;   // Пользователь найден
                }
            }
            fclose($file); // Закрываем файл после чтения
        }

        return false; // Если пользователь не найден
    }

    // Функция для добавления или удаления пользователя в/из файла
    function toggleUserInFile($filePath, $userId) {
        $result = "";
        $users = [];

        if (file_exists($filePath)) {
            // Читаем файл и собираем все строки в массив
            $users = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }

        // Проверяем, есть ли пользователь в массиве
        if (in_array($userId, $users)) {
            // Если есть, удаляем
            $users = array_filter($users, function ($line) use ($userId) {
                return $line != $userId;
            });
            $result = $userId . " удален из списка";
        } else {
            // Если нет, добавляем
            $users[] = $userId;
            $result = $userId . " добавлен в список.";
        }

        // Перезаписываем файл новым содержимым
        file_put_contents($filePath, implode(PHP_EOL, $users) . PHP_EOL);
        return $result;
    }
}
?>
