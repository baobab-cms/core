<?php

declare(strict_types=1);

namespace Modules\Doc\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle au FQCN que `ContentType::modelClass()` dérive d'une clé de Content
 * Type (`Modules\{Key}\Models\{Key}`) — c'est cette convention, et non un
 * enregistrement quelconque, qui permet à `ContentType::forModelClass()` de
 * retrouver le blueprint depuis une simple instance.
 *
 * Volontairement hors autoload : le fichier est chargé par `require_once`
 * depuis le test qui en a besoin. Un modèle nommé `Modules\…` dans l'autoload
 * du package laisserait croire qu'un module existe.
 */
class Doc extends Model
{
    protected $table = 'ct_docs';

    protected $guarded = [];
}
