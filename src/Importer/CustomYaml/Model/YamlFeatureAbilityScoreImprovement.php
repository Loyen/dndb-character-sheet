<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlFeatureAbilityScoreImprovement extends YamlFeature
{
    public function __construct(
        public ?string $name = null,
        public int $level = 1,
        public string $description = '',
        /** @var YamlAbilityScore[] */
        public array $abilities = [],
        /** @var YamlSource[] */
        public array $sources = []
    ) {
    }
}
