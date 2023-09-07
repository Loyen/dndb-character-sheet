<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlFeatureProficiencyImprovement extends YamlFeature
{
    public function __construct(
        public ?string $name = null,
        public int $level = 1,
        public string $description = '',
        public YamlProficiencyCategory $category,
        /** @var string[] */
        public array $profiencies = [],
        /** @var YamlSource[] */
        public array $sources = []
    ) {
    }
}
