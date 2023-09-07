<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlFeatureMovementImprovement extends YamlFeature
{
    public function __construct(
        public ?string $name = null,
        public int $level = 1,
        public string $description = '',
        /** @var array<string, int> */
        public array $movement = [],
        /** @var YamlSource[] */
        public array $sources = []
    ) {
    }
}
