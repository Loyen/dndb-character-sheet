<?php

namespace loyen\DndbCharacterSheet\Importer\DndBeyond\Model;

enum ApiBonusType: int
{
    case Bonus = 1;
    case Set = 9;
    case StackingBonus = 38;
}