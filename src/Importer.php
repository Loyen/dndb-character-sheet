<?php

namespace loyen\DndbCharacterSheet;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use loyen\DndbCharacterSheet\Exception\CharacterAPIException;
use loyen\DndbCharacterSheet\Exception\CharacterException;
use loyen\DndbCharacterSheet\Exception\CharacterFileReadException;
use loyen\DndbCharacterSheet\Exception\CharacterInvalidImportException;
use loyen\DndbCharacterSheet\Model\AbilityType;
use loyen\DndbCharacterSheet\Model\Character;
use loyen\DndbCharacterSheet\Model\CharacterAbility;
use loyen\DndbCharacterSheet\Model\CharacterClass;
use loyen\DndbCharacterSheet\Model\CharacterHealth;
use loyen\DndbCharacterSheet\Model\CharacterMovement;
use loyen\DndbCharacterSheet\Model\CurrencyType;
use loyen\DndbCharacterSheet\Model\Item;
use loyen\DndbCharacterSheet\Model\MovementType;

class Importer
{
    /**
     * @var array<string, mixed> $data
     */
    private array $data;
    /**
     * @var array<int, array<string, mixed>> $modifiers
     */
    private array $modifiers;
    private Character $character;

    public static function importFromApiById(int $characterId): Character
    {
        try {
            $client = new Client([
                'base_uri'  => 'https://character-service.dndbeyond.com/',
                'timeout'   => 4
            ]);

            $response = $client->request('GET', 'character/v5/character/' . $characterId);

            return (new self($response->getBody()))->createCharacter();
        } catch (GuzzleException $e) {
            throw new CharacterAPIException('Could not get a response from DNDBeyond character API. Message: ' . $e->getMessage());
        }
    }

    public static function importFromFile(string $filePath): Character
    {
        $characterFileContent = \file_get_contents($filePath);
        if (!$characterFileContent) {
            throw new CharacterFileReadException($filePath);
        }

        return self::importFromJson($characterFileContent);
    }

    public static function importFromJson(string $jsonString): Character
    {
        return (new self($jsonString))->createCharacter();
    }

    public function __construct(string $jsonString)
    {
        $this->data = \json_decode($jsonString, true)['data'] ?? throw new CharacterInvalidImportException();
    }

    public function createCharacter(): Character
    {
        $this->character = new Character();

        $this->character->setName($this->data['name']);
        $this->character->setInventory($this->getInventory());
        $this->character->setAbilityScores($this->getAbilityScores());
        $this->character->setClasses($this->getClasses());
        $this->character->setLevel($this->getLevel());
        $this->character->setCurrencies($this->getCurrencies());
        $this->character->setHealth($this->getHealth());
        $this->character->setProficiencyBonus($this->getProficiencyBonus());
        $this->character->setMovementSpeeds($this->getMovementSpeeds());
        $this->character->setProficiencies([
            'armor'     => $this->getArmorProficiencies(),
            'languages' => $this->getLanguages(),
            'tools'     => $this->getToolProficiencies(),
            'weapons'   => $this->getWeaponProficiences(),
        ]);

        return $this->character;
    }

    /**
     * @return array<string, CharacterAbility>
     */
    public function getAbilityScores(): array
    {
        $stats = $this->data['stats'];
        $modifiers = $this->getModifiers();
        $statsModifiers = array_filter(
            $modifiers,
            fn ($m) => 1472902489 === $m['entityTypeId'] &&
                       null !== $m['value'] &&
                       1960452172 === $m['componentTypeId']
        );

        $modifiersList = [];
        foreach ($statsModifiers as $statModifier) {
            $entityId = $statModifier['entityId'];
            $modifiersList[$entityId][] = $statModifier['value'];
        }

        foreach ($this->data['bonusStats'] as $bonusStat) {
            if (!empty($bonusStat['value'])) {
                $entityId = $bonusStat['id'];
                $modifiersList[$entityId][] = $bonusStat['value'];
            }
        }

        $overrideList = [];
        foreach ($this->data['overrideStats'] as $overrideStat) {
            if (!empty($overrideStat['value'])) {
                $entityId = $overrideStat['id'];
                $overrideList[$entityId] = $overrideStat['value'];
            }
        }

        $savingThrowsProficiencies = array_column(array_filter(
            $modifiers,
            fn ($m) => $m['type'] === 'proficiency' &&
                       str_ends_with($m['subType'], '-saving-throws')
            ),
            'type',
            'subType'
        );

        foreach ($this->getItemModifiers() as $itemModifier) {
            $entityId = $itemModifier['entityId'];
            if (9 === $itemModifier['modifierTypeId']) {
                $overrideList[$entityId] = $itemModifier['value'];
            } else {
                $modifiersList[$entityId][] = $itemModifier['value'];
            }
        }

        $statsCollection = [];
        foreach ($stats as $stat) {
            $statId = $stat['id'];
            $characterAbilityType = AbilityType::from($statId);
            $savingThrowCode = strtolower($characterAbilityType->name()) . '-saving-throws';

            $ability = new CharacterAbility($characterAbilityType);
            $ability->setValue($stat['value']);
            $ability->setSavingThrowProficient(isset($savingThrowsProficiencies[$savingThrowCode]));

            if (isset($modifiersList[$statId])) {
                $ability->setModifiers($modifiersList[$statId]);
            }

            if (isset($overrideList[$statId])) {
                $ability->setOverrideValue($overrideList[$statId]);
            }

            $statsCollection[$characterAbilityType->name] = $ability;
        }

        return $statsCollection;
    }

    /**
     * @return array<int, string>
     */
    public function getArmorProficiencies(): array
    {
        $modifiers = $this->getModifiers();
        $armors = array_values(array_unique(array_column(array_filter(
                $modifiers,
                fn ($m) => $m['entityTypeId'] === 174869515
            ),
            'friendlySubtypeName'
        )));

        return $armors;
    }

    /**
     * @return array<int, CharacterClass>
     */
    public function getClasses(): array
    {
        $classes = $this->data['classes'];
        $classOptions = array_column($this->data['options']['class'], null, 'componentId');

        // Do not include any of these in the features list
        $skippedFeatures = [
            'Ability Score Improvement',
            'Hit Points',
            'Proficiencies',
            'Fast Movement'
        ];

        $classList = [];
        foreach ($classes as $classPosition => $class) {
            $characterClass = new CharacterClass($class['definition']['name']);
            $characterClass->setLevel($class['level']);

            $classFeatures = $class['definition']['classFeatures'];

            if (isset($class['subclassDefinition'])) {
                $characterClass->setSubName($class['subclassDefinition']['name']);

                $classFeatures = array_merge($classFeatures, $class['subclassDefinition']['classFeatures']);
            }

            foreach ($classFeatures as $feature) {
                $featureName = isset($classOptions[$feature['id']]['definition']['name'])
                    ? $feature['name'] . ' - ' . $classOptions[$feature['id']]['definition']['name']
                    : $feature['name'];

                if (in_array($featureName, $characterClass->getFeatures()) ||
                    $feature['requiredLevel'] > $class['level'] ||
                    in_array($feature['name'], $skippedFeatures)) {
                    continue;
                }

                $characterClass->addFeature($featureName);
            }

            $classList[] = $characterClass;
        }

        return $classList;
    }

    /**
     * @return array<string, int>
     */
    public function getCurrencies(): array
    {
        $currencies = $this->data['currencies'];

        $currencyList = [];
        foreach (CurrencyType::cases() as $currency) {
            $currencyList[$currency->value] = $currencies[$currency->value];
        }

        return $currencyList;
    }

    public function getHealth(): CharacterHealth
    {
        $baseHitPoints = $this->data['baseHitPoints'];

        $healthModifiers = [];
        if (isset($this->data['bonusHitPoints'])) {
            $healthModifiers[] = $this->data['bonusHitPoints'];
        }
        if (isset($this->data['removedHitPoints'])) {
            $healthModifiers[] = -$this->data['removedHitPoints'];
        }

        $constituionScore = $this->character->getAbilityScores()[AbilityType::CON->name];
        $baseHitPoints += $this->character->getLevel() * $constituionScore->getCalculatedModifier();

        return new CharacterHealth(
            $baseHitPoints,
            $healthModifiers,
            $this->data['temporaryHitPoints'] ?? 0,
            $this->data['overrideHitPoints'] ?? null,
        );
    }

    /**
     * @return array<int, Item>
     */
    public function getInventory(): array
    {
        $inventory = $this->data['inventory'];

        $itemList = [];
        foreach ($inventory as $iItem) {
            $iItemDefinition = $iItem['definition'];
            $item = new Item(
                $iItemDefinition['name'],
                $iItemDefinition['filterType']
            );
            $item->setId($iItemDefinition['id']);
            $item->setTypeId($iItemDefinition['entityTypeId']);

            $subType = $iItemDefinition['subType'] ?? $iItemDefinition['type'];

            if ($iItemDefinition['filterType'] !== $subType) {
                $item->setSubType($subType);
            }

            $item->setQuantity($iItem['quantity']);
            $item->setCanAttune($iItemDefinition['canAttune']);
            $item->setIsAttuned($iItem['isAttuned']);
            $item->setIsConsumable($iItemDefinition['isConsumable']);
            $item->setIsEquipped($iItemDefinition['canEquip'] && $iItem['equipped']);
            $item->setIsMagical($iItemDefinition['magic']);

            if (isset($iItemDefinition['damageType'])) {
                $item->setDamageType($iItemDefinition['damageType']);
            }

            if (isset($iItemDefinition['damage']['diceString'])) {
                $item->setDamage($iItemDefinition['damage']['diceString']);
            }

            if (isset($iItemDefinition['range'])) {
                $item->setRange($iItemDefinition['range']);
            }

            if (isset($iItemDefinition['longRange'])) {
                $item->setLongRange($iItemDefinition['longRange']);
            }

            if (isset($iItemDefinition['properties'])) {
                foreach ($iItemDefinition['properties'] as $p) {
                    $item->addProperty($p['name']);
                }
            }

            $itemList[] = $item;
        }

        return $itemList;
    }

    /**
     * @return array<int, string>
     */
    public function getLanguages(): array
    {
        $modifiers = $this->getModifiers();
        $languages = array_values(array_unique(array_column(array_filter(
                $modifiers,
                fn ($m) => $m['type'] === 'language'
            ),
            'friendlySubtypeName'
        )));

        sort($languages);

        return $languages;
    }

    public function getLevel(): int
    {
        return (int) min(20, array_sum(array_column($this->data['classes'], 'level')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getModifiers(): array
    {
        if (isset($this->modifiers)) {
            return $this->modifiers;
        }

        $modifiers = $this->data['modifiers'];

        unset($modifiers['item']);
        $this->modifiers = array_merge(...array_values($modifiers));

        return $this->modifiers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getItemModifiers(): array
    {
        $itemModifiers = array_column($this->data['modifiers']['item'], null, 'componentId');

        $itemModifierList = [];
        foreach ($this->character->getInventory() as $item) {
            $applyModifier = $item->isEquipped() && (!$item->canBeAttuned() || $item->isAttuned());
            if ($applyModifier && isset($itemModifiers[$item->getId()])) {
                $itemModifierList[] = $itemModifiers[$item->getId()];
            }
        }

        return $itemModifierList;
    }

    /**
     * @return array<string, CharacterMovement>
     */
    public function getMovementSpeeds(): array
    {
        $walkingSpeed = $this->data['race']['weightSpeeds']['normal']['walk'];
        $modifiers = $this->getModifiers();

        $walkingSpeedModifierSubTypes = [
            1685, // unarmored-movement
            1697  // speed-walking
        ];

        $walkingModifiers = array_column(array_filter(
                $modifiers,
                fn ($m) => 1 === $m['modifierTypeId'] &&
                                 in_array($m['modifierSubTypeId'], $walkingSpeedModifierSubTypes, true)
            ),
            'value'
        );

        $speedCollection = [
            MovementType::WALK->name() => new CharacterMovement(
                MovementType::WALK,
                $walkingSpeed,
                $walkingModifiers
            )
        ];

        $flyingModifiers = array_filter(
            $modifiers,
            fn ($m) => 9 === $m['modifierTypeId'] && 182 === $m['modifierSubTypeId']
        );

        if (!empty($flyingModifiers)) {
            $flyingSpeed = \max(array_column($flyingModifiers, 'value'));
            $speedCollection[MovementType::FLY->name()] = new CharacterMovement(
                MovementType::FLY,
                $flyingSpeed ?: $walkingSpeed,
                $flyingSpeed ? [ 0 ] : $walkingModifiers
            );
        }

        return $speedCollection;
    }

    public function getProficiencyBonus(): int
    {
        $level = $this->character->getLevel();

        return match (true) {
            $level <= 4 => 2,
            $level <= 8 => 3,
            $level <= 12 => 4,
            $level <= 16 => 5,
            $level <= 20 => 6,
            default => throw new CharacterException('Level out of scope')
        };
    }

    /**
     * @return array<int, string>
     */
    public function getToolProficiencies(): array
    {
        $modifiers = $this->getModifiers();
        $tools = array_values(array_unique(array_column(array_filter(
                $modifiers,
                fn ($m) => $m['entityTypeId'] === 2103445194
            ),
            'friendlySubtypeName'
        )));

        sort($tools);

        return $tools;
    }

    /**
     * @return array<int, string>
     */
    public function getWeaponProficiences(): array
    {
        $modifiers = $this->getModifiers();
        $weaponEntityIdList = [
            660121713, // Type
            1782728300, // Weapon-specific
        ];

        $weapons = array_values(array_unique(array_column(array_filter(
                $modifiers,
                fn ($m) => in_array($m['entityTypeId'], $weaponEntityIdList)
            ),
            'friendlySubtypeName'
        )));

        sort($weapons);

        return $weapons;
    }
}
