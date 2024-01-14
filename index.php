<?php
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
    private $botToken = ""; // 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
    // наш уникальный id в Telegramm - можно воспользоваться @userinfobot при старте он вам его покажет
    private $adminId = 0000000;
    // адрес для запросов к API Telegram
    private $apiUrl = "https://api.telegram.org/bot";
    // Приветствие для админа при старте
    private $helloAdmin = "Приветствую тебя Создатель. 🙏\nНачинаем ждать сообщений от пользователей.";
    // Приветствие для пользователя при старте
    private $helloUser = "Приветствую Вас {username}. 👋\nМы ждем вашего сообщения.\n------\nСпасибо.";
    // Сообщение в случае если админ напишет боту
    private $answerAdmin = "Выберите в контекстном меню функцию Ответить/Reply в сообщении, на которое хотите ответить\n ";

    private $randomCatURL = "https://api.thecatapi.com/v1/images/search";
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
        // если это Старт
        if($this->isStartBot($arrData)) {
            // Определяем кто написал
            $chat_id = $is_admin ? $this->adminId : $chat_id;
            // Выводим приветственное слово
            $hello = $is_admin ? $this->helloAdmin : $this->setTextHello($this->helloUser, $arrData);
            // Отправляем сообщение
            $this->requestToTelegram(array("text" => $hello), $chat_id, "sendMessage");
            $this->requestToTelegram(array("sticker" => "CAACAgIAAxkBAAIB5WWj6MjPeqIZuH8CaALxD8G9KgRgAAKWOwACo33oSNC_B-9StuiSNAQ"), $chat_id, "sendSticker");
        } elseif($this->isSupportBot($arrData)){
            $this->requestToTelegram(array("text" => "Лучшая поддержка это распространение стикеров повсюду ✨\nОднако, если Вы всё таки хотите поддержать меня материально, то тогда что-нибудь придумаю потом)"), $chat_id, "sendMessage");
        } elseif($this->isCatBot($arrData)){
            $url = $this->randomCatURL;
            $json = file_get_contents($url);
            $json_data = json_decode($json, true);
            $this->requestToTelegram(array("text" => $json_data[0]["url"]), $chat_id, "sendMessage");
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
                // Если этонаписал пользователь то перенаправляем админу
                $dataSend = array(
                    'from_chat_id' => $arrData['message']['from']['id'],
                    'message_id' => $arrData['message']['message_id'],
                );
                $this->requestToTelegram($dataSend, $this->adminId, "forwardMessage");
                $this->requestToTelegram(array("text" => "ID:".$arrData['message']['from']['id']), $this->adminId, "sendMessage");
            }
        }
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
        //$this->setFileLog($data);
        return json_decode(file_get_contents($data), TRUE);
    }

    private function setFileLog($data) {
        $data = json_decode(file_get_contents($data), TRUE);
        $fh = fopen('log.txt', 'a') or die('can\'t open file');
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

}
?>
