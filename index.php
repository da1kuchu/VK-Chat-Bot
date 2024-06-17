<?php

// @author - vk.com/thedudeone

require_once "rabbit/autoload.php";
use DigitalStar\vk_api\vk_api;

const VK_KEY = "токен";
const CONFIRM_STR = "уникальный_ключ";
const VERSION = "5.131";

$vk = vk_api::create(VK_KEY, VERSION)->setConfirm(CONFIRM_STR);

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['type']) || !isset($data['object']['message'])) {
    exit();
}

$vk->initVars($peer_id, $message, $payload, $user_id, $type, $data);

$group = $peer_id > 2000000000;

if (!$group) {
    $vk->reply("❌ К сожалению, я не разработан для выполнения команд в личных сообщениях. Перейдите в беседу, где я работаю. Чтобы попасть туда, обратитесь к @thedudeone(Основателю)");
    exit();
}

$admins = ["айди"];
$super = ["айди"];

if ($type == "confirmation") {
    echo CONFIRM_STR;
    exit();
}

function loadParticipants() {
    $file = 'vk.json';
    if (file_exists($file)) {
        $contents = file_get_contents($file);
        return json_decode($contents, true);
    }
    return [];
}

function saveParticipants($participants) {
    $file = 'vk.json';
    $json = json_encode($participants);
    file_put_contents($file, $json);
}

function getMentionId($mention) {
    if (preg_match('/\[id(\d+)\|/', $mention, $matches)) {
        return $matches[1];
    } elseif (preg_match('/\[club(\d+)\|/', $mention, $matches)) {
        return '-' . $matches[1];
    } elseif (preg_match('/\[public(\d+)\|/', $mention, $matches)) {
        return '-' . $matches[1];
    }
    return null;
}

function getUserInfo($user_id) {
    return [
        'id' => $user_id,
        'registration_date' => date('Y-m-d H:i:s')
    ];
}

function isParticipantRegistered($user_id) {
    $participants = loadParticipants();
    return isset($participants[$user_id]);
}

function registerParticipant($user_info) {
    $participants = loadParticipants();
    $participants[$user_info['id']] = $user_info;
    saveParticipants($participants);
}

function kickUser($chat_id, $user_id, $vk) {
    try {
        $vk->request('messages.removeChatUser', [
            'chat_id' => $chat_id - 2000000000,
            'user_id' => $user_id
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getRandomUserId($peer_id, $vk) {
    try {
        $response = $vk->request('messages.getConversationMembers', [
            'peer_id' => $peer_id
        ]);
        $members = $response['profiles'];
        $random_member = $members[array_rand($members)];
        return $random_member['id'];
    } catch (Exception $e) {
        return null;
    }
}

switch ($type) {
    case "message_new":
        if (isset($message) && !empty($message) && $message[0] == "!") {
            $message = strtolower(trim(ltrim($message, "!")));
            $parts = explode(" ", $message, 2);
            $command = $parts[0];
            $argument = isset($parts[1]) ? $parts[1] : null;

            switch ($command) {
                case "проф":
                    $user_info = $vk->request('users.get', ['user_ids' => $user_id])[0];
                    $first_name = $user_info['first_name'];
                    $last_name = $user_info['last_name'];
                    $status = (in_array($user_id, $super)) ? "🔰" : ((in_array($user_id, $admins)) ? "🟢" : "🔴");
                    $vk->reply("✨ Ваш профиль:\n\n🆔 Ваш ID: $user_id\n💬 Айди беседы: $peer_id\n👤 Ваше имя: $first_name $last_name\n🔖 Статус админа: $status");
                    break;
                
                case "войти":
                    if (isParticipantRegistered($user_id)) {
                        $vk->reply("⚠️ Вы уже зарегистрированы в боте, не повторяйте.");
                    } else {
                        $user_info = getUserInfo($user_id);
                        registerParticipant($user_info);
                        $vk->reply("✅ Вы успешно зарегистрированы!");
                    }
                    break;

                case "бан":
                    if ($argument) {
                        $kick_user_id = intval($argument);
                        if (in_array($user_id, $super) || (in_array($user_id, $admins) && !in_array($kick_user_id, $super))) {
                            if (kickUser($peer_id, $kick_user_id, $vk)) {
                                $admin_info = $vk->request('users.get', ['user_ids' => $user_id])[0];
                                $kick_user_info = $vk->request('users.get', ['user_ids' => $kick_user_id])[0];
                                $admin_name = $admin_info['first_name'] . ' ' . $admin_info['last_name'];
                                $kick_user_name = $kick_user_info['first_name'] . ' ' . $kick_user_info['last_name'];
                                $vk->reply("📝 Админ [id$user_id|$admin_name] заблокировал участника [id$kick_user_id|$kick_user_name] навсегда из чата.");
                            } else {
                                $vk->reply("❌ Не удалось исключить участника с ID $kick_user_id. Убедитесь, что ID правильный и у вас есть права администратора.");
                            }
                        } else {
                            $vk->reply("🚫 У вас нет прав на выполнение этой команды. Либо этот участник имеет высший статус.");
                        }
                    } else {
                        $vk->reply("⚠️ Укажите ID участника для выполнения команды.");
                    }
                    break;

                case "айди":
                    if ($argument) {
                        $mention_id = getMentionId($argument);
                        if ($mention_id) {
                            $vk->reply("🔍 Цифровой ID пользователя:\n$mention_id");
                        } else {
                            $vk->reply("❌ Не удалось получить ID пользователя. Убедитесь, что упоминание верное.");
                        }
                    } else {
                        $vk->reply("⚠️ Укажите упоминание пользователя для выполнения команды.");
                    }
                    break;

                case "товары":
                    $vk->reply("💣 Список всех товаров и их цены:\n\nИгрок - 0р\nВип - 5р\nПремиум - 10р\nКреатив - 20р\nВарвар - 30р\nПират - 50р\nИмператор - 70р\nКнязь - 100р\nБродяга - 130р\n\nМодератор - не продаётся\nГлавный Администратор - не продаётся\nОснователь - не продаётся\n\nДонат кейс 1 шт - 30р\nДонат кейс 5 шт - 100р\n\n100 000 монет - 30р\n500 000 монет - 100р\n1 000 000 монет - 200р\n\nRCON START - 80р\nRCON VIP - 300р");
                    break;

                case "кто":
                    if ($argument) {
                        $random = getRandomUserId($peer_id, $vk);
                        if ($random) {
                            $vk->reply("🔍 Я думаю, [id$random|этот участник] $argument");
                        } else {
                            $vk->reply("❌ Не удалось получить список участников. Убедитесь, что команда введена в беседе.");
                        }
                    } else {
                        $vk->reply("⚠️ Укажите текст для выполнения команды.");
                    }
                    break;

                case "состав":
                    $vk->reply("👨🏻‍💼 Полный состав сервера:\n\n– Основатели ::\n@thedudeone(dudeone) 🔰\n\n– Главные Администраторы ::\n@vk.simbyyy(simbyyy) 🟢\n@hinayash(hinalove) 🟢\n\n– Модераторы ::\nnone\n\n– Стажёры ::\nnone");
                    break;

                case "панель":
                    break;

                case "block":
                    break;

                case "помощь":
                    $vk->reply("🆘 Помощь по командам:\n\n– Доступные команды:\n!проф - Показать ваш профиль\n!войти - Зарегистрироваться в боте\n!айди [упоминание] - Получить ID пользователя\n!кто [текст] - Случайный выбор участника\n!помощь - Показать список команд\n!товары - Все товары сервера\n!состав - Весь состав сервера\n\n– Админ команды:\n!бан [ID] - Заблокировать участника из чата навсегда\n!панель - Админ панель\n!block [ID] - Добавить участника в черный список RCON");
                    break;
               default:
                 $vk->reply("❌ Неизвестная команда, введите !помощь для ознакомления доступными командами.");
            }
            echo 'ok';
            exit();
        }
        break;

    default:
        exit();
}
