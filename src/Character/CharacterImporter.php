<?php

namespace loyen\DndbCharacterSheet\Character;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use loyen\DndbCharacterSheet\Character\Exception\CharacterInvalidImportException;
use loyen\DndbCharacterSheet\Character\Model\Character;
use loyen\DndbCharacterSheet\Character\Model\CharacterAbility;
use loyen\DndbCharacterSheet\Character\Model\CharacterAbilityTypes;
use loyen\DndbCharacterSheet\Character\Model\CharacterMovement;
use loyen\DndbCharacterSheet\Character\Model\CharacterMovementTypes;

class CharacterImporter
{
    public static function importFromApiById(int $characterId): Character
    {
        try {
            $client = new Client([
                'base_uri'  => 'https://character-service.dndbeyond.com/',
                'timeout'   => 2
            ]);

            $response = $client->request('GET', 'character/v5/character/' . $characterId);

            return self::createCharacterFromJson($response->getBody());
        } catch (GuzzleException $e) {
            \trigger_error('Could not get a response from DNDBeyond character API. Message: ' . $e->getMessage());
        }
    }

    public static function importFromJson(string $jsonString): Character
    {
        return self::createCharacterFromJson($jsonString);
    }

    private static function createCharacterFromJson(string $jsonString): Character
    {
        $jsonData = \json_decode($jsonString, true)['data'] ?? throw new CharacterInvalidImportException();

        $character = new Character();

        $character->setName($jsonData['name']);
        $character->setAbilityScores(self::extractAbilityScoresFromData($jsonData));
        $character->setClasses(self::extractClassesFromData($jsonData));
        $character->setProficiencyBonus(self::extractProficiencyBonusFromData($jsonData));
        $character->setMovementSpeeds(self::extractMovementSpeedsFromData($jsonData));
        $character->setProficiencies([
            'armor'     => self::extractArmorProficienciesFromData($jsonData),
            'languages' => self::extractLanguagesFromData($jsonData),
            'tools'     => self::extractToolProficienciesFromData($jsonData),
            'weapons'   => self::extractWeaponProficienciesFromData($jsonData),
        ]);

        return $character;
    }

    public static function extractMovementSpeedsFromData(array $data): array
    {
        $walkingSpeed = $data['race']['weightSpeeds']['normal']['walk'];
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));

        $walkingSpeedModifierSubTypes = [
            1685, // unarmored-movement
            1697  // speed-walking
        ];

        $walkingModifiers = array_column(array_filter(
                $flatModifiers,
                fn ($m) => 1 === $m['modifierTypeId'] &&
                                 in_array($m['modifierSubTypeId'], $walkingSpeedModifierSubTypes, true)
            ),
            'value'
        );

        $speedCollection = [
            new CharacterMovement(
                CharacterMovementTypes::from('walk'),
                $walkingSpeed,
                $walkingModifiers
            )
        ];

        $flyingModifiers = array_filter(
            $flatModifiers,
            fn ($m) => 9 === $m['modifierTypeId'] && 182 === $m['modifierSubTypeId']
        );

        if (!empty($flyingModifiers)) {
            $flyingSpeed = \max(array_column($flyingModifiers, 'value'));
            $speedCollection[] = new CharacterMovement(
                CharacterMovementTypes::from('fly'),
                $flyingSpeed ?: $walkingSpeed,
                $flyingSpeed ? [ 0 ] : $walkingModifiers
            );
        }

        return $speedCollection;
    }

    public static function extractProficiencyBonusFromData(array $data): int
    {
        $level = min(20, array_sum(array_column($data['classes'], 'level')));

        return match (true) {
            $level <= 4 => 2,
            $level <= 8 => 3,
            $level <= 12 => 4,
            $level <= 16 => 5,
            $level <= 20 => 6
        };
    }

    public static function extractAbilityScoresFromData(array $data): array
    {
        $stats = $data['stats'];
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));

        $statsEntityTypeId = 1472902489;
        $statsModifiers = array_filter(
            $flatModifiers,
            fn ($m) => $m['entityTypeId'] === $statsEntityTypeId
        );

        $modifiersList = [];
        foreach ($statsModifiers as $statModifier) {
            $entityId = $statModifier['entityId'];
            $modifiersList[$entityId][] = $statModifier['value'];
        }

        foreach ($data['bonusStats'] as $bonusStat) {
            if (!empty($bonusStat['value'])) {
                $entityId = $bonusStat['id'];
                $modifiersList[$entityId][] = $bonusStat['value'];
            }
        }

        $overrideList = [];
        foreach ($data['overrideStats'] as $overrideStat) {
            if (!empty($overrideStat['value'])) {
                $entityId = $overrideStat['id'];
                $overrideList[$entityId] = $overrideStat['value'];
            }
        }

        $savingThrowsProficiencies = array_column(array_filter(
            $flatModifiers,
            fn ($m) => $m['type'] === 'proficiency' &&
                       str_ends_with($m['subType'], '-saving-throws')
            ),
            'type',
            'subType'
        );

        $statsCollection = [];
        foreach ($stats as $stat) {
            $statId = $stat['id'];
            $characterAbilityType = CharacterAbilityTypes::from($statId);
            $savingThrowCode = strtolower($characterAbilityType->name()) . '-saving-throws';

            $statsCollection[] = new CharacterAbility(
                $characterAbilityType,
                $stat['value'],
                $modifiersList[$statId] ?? [],
                $overrideList[$statId] ?? null,
                isset($savingThrowsProficiencies[$savingThrowCode])
            );
        }

        return $statsCollection;
    }

    public static function extractLanguagesFromData(array $data): array
    {
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));
        $languages = array_values(array_unique(array_column(array_filter(
                $flatModifiers,
                fn ($m) => $m['type'] === 'language'
            ),
            'friendlySubtypeName'
        )));

        sort($languages);

        return $languages;
    }

    public static function extractToolProficienciesFromData(array $data): array
    {
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));
        $tools = array_values(array_unique(array_column(array_filter(
                $flatModifiers,
                fn ($m) => $m['entityTypeId'] === 2103445194
            ),
            'friendlySubtypeName'
        )));

        sort($tools);

        return $tools;
    }

    public static function extractArmorProficienciesFromData(array $data): array
    {
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));

        $armors = array_values(array_unique(array_column(array_filter(
                $flatModifiers,
                fn ($m) => $m['entityTypeId'] === 174869515
            ),
            'friendlySubtypeName'
        )));

        return $armors;
    }

    public static function extractWeaponProficienciesFromData(array $data): array
    {
        $modifiers = $data['modifiers'];

        $flatModifiers = array_merge(...array_values($modifiers));
        $weaponEntityIdList = [
            660121713, // Type
            1782728300, // Weapon-specific
        ];

        $weapons = array_values(array_unique(array_column(array_filter(
                $flatModifiers,
                fn ($m) => in_array($m['entityTypeId'], $weaponEntityIdList)
            ),
            'friendlySubtypeName'
        )));

        sort($weapons);

        return $weapons;
    }

    public static function extractClassesFromData(array $data): array
    {
        $classes = $data['classes'];
        $classOptions = array_column($data['options']['class'], null, 'componentId');

        // Do not include any of these in the features list
        $skippedFeatures = [
            'Ability Score Improvement',
            'Hit Points',
            'Proficiencies',
            'Fast Movement'
        ];

        $classList = [];
        foreach ($classes as $classPosition => $class) {
            $level = $class['level'];
            $name = $class['definition']['name'];

            $classList[$classPosition] = [
                'level' => $level,
                'name' => $name
            ];

            $classFeatures = $class['definition']['classFeatures'];

            if (isset($class['subclassDefinition'])) {
                $classList[$classPosition]['subName'] = $class['subclassDefinition']['name'];

                $classFeatures = array_merge($classFeatures, $class['subclassDefinition']['classFeatures']);
            }

            $unlockedClassFeatures = \array_filter(
                $classFeatures,
                fn ($f) => $f['requiredLevel'] <= $level &&
                           !in_array($f['name'], $skippedFeatures)
            );

            foreach ($unlockedClassFeatures as &$unlockedFeature) {
                if (isset($classOptions[$unlockedFeature['id']]['definition']['name'])) {
                    $unlockedFeature['name'] = sprintf(
                        '%s - %s',
                        $unlockedFeature['name'],
                        $classOptions[$unlockedFeature['id']]['definition']['name']
                    );
                }
            }

            usort($unlockedClassFeatures, fn($a, $b) => $a['name'] <=> $b['name']);

            $classList[$classPosition]['features'] = array_values(array_unique(array_column($unlockedClassFeatures, 'name')));
        }

        return $classList;
    }
}
