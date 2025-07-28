<?php

declare(strict_types=1);

namespace WhiteeCattt\QuickShulkers;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\sound\ShulkerBoxCloseSound;
use pocketmine\world\sound\ShulkerBoxOpenSound;

class Main extends PluginBase implements Listener {
    public int $shulkerTypeId;
    public int $dyedShulkerTypeId;

    public function onEnable(): void {
        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->shulkerTypeId = VanillaBlocks::SHULKER_BOX()->asItem()->getTypeId();
        $this->dyedShulkerTypeId = VanillaBlocks::DYED_SHULKER_BOX()->asItem()->getTypeId();
    }

    public function onItemUse(PlayerItemUseEvent $event): void {
        $item = $event->getItem();
        $typeId = $item->getTypeId();

        if ($typeId !== $this->shulkerTypeId && $typeId !== $this->dyedShulkerTypeId) return;

        $player = $event->getPlayer();
        $shulker = clone $item;

        $player->getInventory()->setItemInHand(VanillaItems::AIR());

        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName($item->hasCustomName() ? $item->getCustomName() : "Ящик шалкера");

        $inventory = $menu->getInventory();
        $position = $player->getPosition();
        $world = $position->getWorld();

        $tempTile = new \pocketmine\block\tile\ShulkerBox(
            $world,
            new Vector3($position->getFloorX(), $position->getFloorY(), $position->getFloorZ())
        );

        $tempTile->copyDataFromItem($shulker);
        $inventory->setContents($tempTile->getInventory()->getContents());

        $menu->setListener(function (InvMenuTransaction $transaction): InvMenuTransactionResult {
            $typeId = $transaction->getAction()->getTargetItem()->getTypeId();

            if ($typeId === $this->shulkerTypeId || $typeId === $this->dyedShulkerTypeId) {
                return $transaction->discard();
            }

            return $transaction->continue();
        });

        $menu->setInventoryCloseListener(function (Player $player) use ($tempTile, $shulker, $menu): void {
            $tempTile->getInventory()->setContents($menu->getInventory()->getContents());

            $newShulkerItem = clone $shulker;
            $nbt = $tempTile->getCleanedNBT();
            if ($nbt !== null) {
                $newShulkerItem->setNamedTag($nbt);
            }

            $position = $player->getPosition();
            $world = $position->getWorld();
            $playerInventory = $player->getInventory();

            if ($playerInventory->getItemInHand()->isNull()) {
                $playerInventory->setItemInHand($newShulkerItem);
            } else if ($playerInventory->canAddItem($newShulkerItem)) {
                $playerInventory->addItem($newShulkerItem);
            } else {
                $location = new Location($position->getX(), $position->getY(), $position->getZ(), $world, 0, 0);

                $itemEntity = new ItemEntity($location, $newShulkerItem);
                $itemEntity->spawnToAll();
            }

            $world->addSound($position, new ShulkerBoxCloseSound(), [$player]);
            $tempTile->close();
        });

        $world->addSound($position, new ShulkerBoxOpenSound(), [$player]);
        $menu->send($player);
    }
}
