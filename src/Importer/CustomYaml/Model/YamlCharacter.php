<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlCharacter
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $abilityScores,
        public readonly mixed $race,
        public readonly mixed $classes,
        public readonly mixed $background,
        public readonly mixed $inventory,
        public readonly mixed $wallet
    ) {}

    public static function fromYaml(string $yaml): ?self
    {
        $data = yaml_parse($yaml);

        if (empty($data)) {
            return null;
        }

        return new self(
            $data['name'],
            $data['abilityScores'],
            $data['race'],
            $data['classes'],
            $data['background'],
            $data['inventory'],
            $data['wallet']
        );
    }
}
