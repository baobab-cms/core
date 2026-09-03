<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marquage du contenu de démonstration (spec 03 §7, spec 19 §7.3) — la trace
 * qui rend le retrait en un geste possible, et que M8 point 3 Pass D2a pose
 * (suivi n° 249).
 *
 * **Pourquoi une table et non une colonne.** Les entrées de démonstration
 * vivent dans des tables `ct_*` **générées** : y ajouter un `is_demo`
 * obligerait le générateur à le produire pour tous les Content Types du
 * monde, dont aucun n'en a l'usage. Le patron du Core pour désigner « des
 * lignes quelconques » est la table de liaison polymorphique — `media_usages`,
 * `revisions`, `seo_meta` la suivent déjà.
 *
 * **Pourquoi une seule table pour deux natures.** Le seeder ne fait pas que
 * créer : il **modifie** aussi des réglages existants (page d'accueil,
 * assignation d'un emplacement de menu). Le retrait ne peut pas traiter les
 * deux pareil — on supprime ce qui a été créé, on **restaure** ce qui a été
 * modifié — mais les deux sont la même question posée au même moment : « la
 * démonstration a-t-elle touché à ceci ? ». Deux tables auraient dédoublé le
 * modèle et le nettoyage pour une seule ligne de discriminant.
 *
 * L'index est **nommé explicitement** : `morphs()` dérive un nom que le
 * préfixe de tables allonge, et MySQL plafonne les identifiants à 64
 * caractères — c'est exactement ce qui casse au n° 232.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_content', function (Blueprint $table): void {
            $table->id();

            // Une création : la ligne posée par le seeder, qui partira.
            $table->string('demoable_type')->nullable();
            $table->unsignedBigInteger('demoable_id')->nullable();

            // Une modification : le réglage touché et ce qu'il valait avant,
            // pour que le retrait sache restaurer plutôt que deviner.
            $table->string('setting_key')->nullable();
            $table->json('previous_value')->nullable();

            $table->timestamps();

            $table->index(['demoable_type', 'demoable_id'], 'demo_content_demoable_index');
            $table->unique('setting_key', 'demo_content_setting_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_content');
    }
};
