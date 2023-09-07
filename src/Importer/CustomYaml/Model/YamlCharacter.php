<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlCharacter
{
    public function __construct(
        public readonly string $name,
    ) {
    }

    public static function fromYaml(string $yaml): ?self
    {
        $data = \yaml_parse($yaml);

        if (empty($data)) {
            return null;
        }

        return new self(
            $data['name'],
        );
    }
}
