<?php

namespace loyen\DndbCharacterSheet\Importer\CustomYaml\Model;

class YamlFeature
{
    public function __construct(
        public ?string $name = null,
        public int $level = 1,
        public string $description = '',
        /** @var YamlSource[] */
        public array $sources = []
    ) {
    }
}
