<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

use loyen\DndbCharacterSheet\Model\SourceMaterial;

class YamlFeatureAbilityScoreImprovement extends YamlFeature
{
    public function __construct(
        public ?string $name = null,
        public int $level = 1,
        public string $description = '',
        /** @var YamlAbilityScore[] */
        public array $abilities = [],
        /** @var SourceMaterial[] */
        public array $sources = []
    ) {}
}
