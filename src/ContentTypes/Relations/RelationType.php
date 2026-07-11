<?php

declare(strict_types=1);

namespace Baobab\ContentTypes\Relations;

/**
 * Les 4 types de relation de spec 02 §5. Contrairement au catalogue de
 * champs (FieldRegistry, extensible par les modules), ce catalogue est
 * fermé — la spec ne décrit pas les relations comme un point d'extension.
 */
enum RelationType: string
{
    case OneToOne = 'one_to_one';
    case OneToMany = 'one_to_many';
    case ManyToMany = 'many_to_many';
    case Polymorphic = 'polymorphic';
}
