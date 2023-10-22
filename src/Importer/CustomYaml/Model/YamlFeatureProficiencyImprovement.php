<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

use loyen\DndbCharacterSheet\Model\SourceMaterial;

class YamlFeatureProficiencyImprovement extends YamlFeature
{
    public function __construct(
        public ?string $name,
        public int $level,
        public string $description,
        public YamlProficiencyCategory $category,
        /** @var string[] */
        public array $profiencies = [],
        /** @var SourceMaterial[] */
        public array $sources = []
    ) {}
}
