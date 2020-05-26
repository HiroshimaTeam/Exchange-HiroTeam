<?php
#██╗░░██╗██╗██████╗░░█████╗░████████╗███████╗░█████╗░███╗░░░███╗
#██║░░██║██║██╔══██╗██╔══██╗╚══██╔══╝██╔════╝██╔══██╗████╗░████║
#███████║██║██████╔╝██║░░██║░░░██║░░░█████╗░░███████║██╔████╔██║
#██╔══██║██║██╔══██╗██║░░██║░░░██║░░░██╔══╝░░██╔══██║██║╚██╔╝██║
#██║░░██║██║██║░░██║╚█████╔╝░░░██║░░░███████╗██║░░██║██║░╚═╝░██║
#╚═╝░░╚═╝╚═╝╚═╝░░╚═╝░╚════╝░░░░╚═╝░░░╚══════╝╚═╝░░╚═╝╚═╝░░░░░╚═╝
namespace HiroTeam\exchange;

use HiroTeam\exchange\Task\exchangeTask;
use HiroTeam\exchange\forms\SimpleForm;
use HiroTeam\exchange\forms\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;


class ExChange extends PluginBase implements Listener
{
    public $config;
    public $economyapi;
    public $db;
    public $item = [];
    public $id = [];
    public $itemname = [];

    public function onEnable(): void
    {
        $this -> getServer() -> getPluginManager() -> registerEvents($this, $this);
        $this -> economyapi = $this -> getServer() -> getPluginManager() -> getPlugin("EconomyAPI");
        @mkdir($this -> getDataFolder());
        if (! file_exists($this -> getDataFolder() . "config.yml")) {
            $this -> saveResource('config.yml');
        }
        $this -> config = new Config($this -> getDataFolder() . "config.yml", Config::YAML);
        $this -> db = new \SQLite3($this -> getDataFolder() . "Exchange.db");
        $this -> db -> exec("CREATE TABLE IF NOT EXISTS exchange (item TEXT, price FLOAT, quota INT);");
        $this -> StartExChange();
        $this -> getScheduler() -> scheduleDelayedRepeatingTask(new exchangeTask($this), $this -> config -> get("uptime") * 1200, $this -> config -> get("uptime") * 1200);
    }
    public function StartExChange(): void
    {
        $quota = 0;
        foreach ($this -> getAllAdd() as $item => $parametre) {
            if (! $this -> IsItemInData($item)) {
                $setitem = $this -> db -> prepare("INSERT INTO exchange (item, price, quota) VALUES (:item, :price, :quota);");
                $setitem -> bindValue(":item", $item);
                $setitem -> bindValue(":price", $parametre["startprice"]);
                $setitem -> bindValue(":quota", $quota);
                $setitem -> execute();
            }
        }
    }
    public function getAllAdd(): array
    {
        $all = $this -> config -> getAll()["add"];
        return $all;
    }
    public function IsItemInData($item): bool
    {
        $result = $this -> db -> query("SELECT item FROM exchange WHERE item='$item';");
        $array = $result -> fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }
    public function CountPlayerItem(array $item, Player $player): int
    {
        $result = Item ::get($item[0], $item[1]);
        $inventory = $player -> getInventory();
        $number = 0;
        foreach ($inventory -> getContents() as $slot => $item) {
            if ($item -> getId() === $result -> getId() and $item -> getDamage() === $result -> getDamage()) {
                $number = $number + $item -> getCount();
            }
        }
        return $number;
    }
    public function getItem(array $item, $number): Item
    {
        $result = Item ::get($item[0], $item[1], $number);
        return $result;
    }
    public function getBoursePrice($item): float
    {
        $result = $this -> db -> query("SELECT price FROM exchange WHERE item = '$item';");
        $resultArr = $result -> fetchArray(SQLITE3_ASSOC);
        return (float)$resultArr["price"];
    }
    public function remove_price($item, $price): void
    {
        $remove = $this -> getBoursePrice($item) - $price;
        $stmt = $this -> db -> prepare("UPDATE exchange SET price = $remove WHERE item= '$item';");
        $stmt -> execute();
    }

    public function add_price($item, $price): void
    {
        $add = $this -> getBoursePrice($item) + $price;
        $stmt = $this -> db -> prepare("UPDATE exchange SET price = $add WHERE item= '$item';");
        $stmt -> execute();
    }

    public function getBourseQuota($item): int
    {
        $result = $this -> db -> query("SELECT quota FROM exchange WHERE item = '$item';");
        $resultArr = $result -> fetchArray(SQLITE3_ASSOC);
        return (int)$resultArr["quota"];
    }
    public function add_quota($item, $quota): void
    {
        $add = $this -> getBourseQuota($item) + $quota;
        $stmt = $this -> db -> prepare("UPDATE exchange SET quota = $add WHERE item= '$item';");
        $stmt -> execute();
    }

    public function reset_quota($item): void
    {
        $stmt = $this -> db -> prepare("UPDATE exchange SET quota = 0 WHERE item= '$item';");
        $stmt -> execute();
    }
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if ($command -> getName() === "exchange") {
            if ($sender instanceof Player) {
                $this -> OpenShopListUI($sender);
                return true;
            }
            return false;
        }
    }
    public function OpenShopListUI($player)
    {
        $form = $this -> createSimpleForm(function (Player $player, $data = null) {
            $target = $data;
            foreach ($this -> getAllAdd() as $item => $parametre) {
                if ($target === $item) {
                    $getitem = $parametre["itemid"];
                }
            }
            if ($target === null) {
                return true;
            }
            if ($this -> CountPlayerItem($getitem, $player) === 0) {
                $player -> sendMessage($this -> config -> get("noitem"));
                return true;
            }
            $this -> item[$player -> getName()] = $target;
            $this -> OpenShopItemUI($player);
        });
        $form -> setTitle($this -> config -> get("title"));
        $form -> setContent($this -> config -> get("shoptext"));
        $result = $this -> db -> query("SELECT item FROM exchange ORDER BY price DESC;");

        while ($resultArr = $result -> fetchArray(SQLITE3_ASSOC)) {
            $item = $resultArr['item'];
            $bouseprice = $this -> getBoursePrice($item);
            $form -> addButton($this -> formatMessage($this -> config -> get("button"), $item, $bouseprice), -1, "", $item);
        }
        $form -> sendToPlayer($player);
        return $form;
    }
    public function OpenShopItemUI($player)
    {
        $form = $this -> createCustomForm(function (Player $player, array $data = null) {
            $result = $data[0];
            if ($result === null) {
                return true;
            }
            $player -> getInventory() -> removeItem($this -> getItem($this -> id[$player -> getName()], $data[1]));
            $price = $this -> getBoursePrice($this -> item[$player -> getName()]);
            $this -> economyapi -> addMoney($player, $price * $data[1]);
            $this -> add_quota($this -> item[$player -> getName()], $data[1]);
            $player -> sendMessage($this -> succesformatMessage($this -> config -> get("successell"), $data[1], $this -> itemname[$player -> getName()], $price * $data[1]));
            unset($this -> item[$player -> getName()]);
            unset($this -> id[$player -> getName()]);
            unset($this -> itemname[$player -> getName()]);
        });
        foreach ($this -> getAllAdd() as $item => $parametre) {
            if ($item === $this -> item[$player -> getName()]) {
                $list[] = $item;
                $getitem = $parametre["itemid"];
                $this -> id[$player -> getName()] = $getitem;
                $this -> itemname[$player -> getName()] = $item;
            }
        }
        $price = $this -> getBoursePrice($this -> item[$player -> getName()]);
        $form -> addDropdown($this -> formatMessage($this -> config -> get("shopitemtext"), $this -> item[$player -> getName()], $price), $list);
        $form -> addSlider($this -> config -> get("numberofitem"), 0, $this -> CountPlayerItem($getitem, $player), 1);
        $form -> sendToPlayer($player);
        return $form;
    }
    public function formatMessage($message, $itemname, $itemprice): string
    {
        $message = str_replace("{item}", $itemname, $message);
        $message = str_replace("{price}", $itemprice, $message);
        return $message;
    }
    public function succesformatMessage($message, $number, $itemname, $total): string
    {
        $message = str_replace("{item}", $itemname, $message);
        $message = str_replace("{number}", $number, $message);
        $message = str_replace("{total}", $total, $message);
        return $message;
    }
    public function TaskDo(): void
    {
        foreach ($this -> getAllAdd() as $item => $parametre) {
            $quota = $this -> getBourseQuota($item);
            $price = $this -> getBoursePrice($item);

            if ($quota <= $parametre["quotamin"]) {
                if ($price < $parametre["maxprice"]) {
                    $this -> add_price($item, $parametre["addition"]);
                }
            }
            if ($quota >= $parametre["quotamax"]) {
                if ($price > $parametre["minprice"]) {
                    $this -> remove_price($item, $parametre["remove"]);
                }
            }
            $this -> reset_quota($item);
        }
    }
    #thanks jojoe77777
    public function createCustomForm(callable $function = null) : CustomForm {
        return new CustomForm($function);
    }
    public function createSimpleForm(callable $function = null) : SimpleForm {
        return new SimpleForm($function);
    }
}
