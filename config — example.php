<?php
define('CFG_TOKEN', ''); // 123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11 - @BotFather bot token
define('CFG_ADMIN', ''); // 00000000 - @userinfobot admin user id
define('CFG_API', 'https://api.telegram.org/bot'); // telegram api url
define('CFG_BLACKLIST', 'blacklist.txt'); // blacklist filename, ban with /ban {id}
define('CFG_LOGS', 'log.txt'); // log filename
define('CFG_LOGGING', false); // enable logging
define('CFG_CAT', 'https://api.thecatapi.com/v1/images/search');
define('CFG_MONEY', [
                    "visa" => "",
                    "mastercard" => "",
                    "mir" => "",
                    "donationalerts" => "",
                    "ton" => "",
                    "btc" => "",
                    "usdt" => ""
                ]);
define('CFG_STICKERS', [
                [
                    "text" => "AI Stickers - Part 1",
                    "link" => "https://t.me/addstickers/AiStickersPack",
                    "sticker" => "CAACAgIAAxkBAAJuTmWljUpkX2mzAmYWRIFwfEYwll92AAL6PAACJXDQSCX0QUQnZkltNAQ"
                ],
                [
                    "text" => "AI Stickers - Part 2",
                    "link" => "https://t.me/addstickers/AiStickersPack2",
                    "sticker" => "CAACAgIAAxkBAAJuTGWljP8EtmeFFCJKR9pMBHRSLlH5AAKhPQACleYJSfdQl8v0C7e5NAQ"
                ],
                [
                    "text" => "Арты Пепачки",
                    "link" => "https://t.me/addstickers/PepachkaArts",
                    "sticker" => "CAACAgIAAxkBAAJuUGWljVWQl6iGR-wbmxYgJP5iThPoAAIKPAAChqvwSP6jd3zQEUVNNAQ"
                ]
            ]);
?>
