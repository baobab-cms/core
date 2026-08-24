<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal **métier** des e-mails (spec 13 §4.1) — distinct du journal
 * technique (spec 12 §9) et du journal d'audit (spec 12 §8, qui trace des
 * actions d'administration et non chaque envoi).
 *
 * Une ligne par e-mail, écrite en `queued` au moment du dispatch et amenée à
 * `sent` ou `failed` par le job. Le `mailer` est celui réellement utilisé,
 * relevé à l'envoi et non à la mise en file : un changement de transport entre
 * les deux ferait mentir la valeur figée trop tôt.
 *
 * `body` est **nul par défaut** (§4.1 : « le corps rendu n'est pas stocké par
 * défaut », volume et données personnelles) et ne se remplit que si
 * `baobab.mail.log_body` est activé — avec sa propre rétention, plus courte
 * que celle des lignes elles-mêmes.
 *
 * Les quatre index servent les quatre filtres de l'écran (§4.2) ; celui sur
 * `created_at` sert en plus la purge programmée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_log', function (Blueprint $table): void {
            $table->id();
            $table->string('template_key')->index();
            $table->string('recipient')->index();
            $table->string('subject');
            $table->string('status')->index();
            $table->text('error')->nullable();
            $table->string('mailer')->nullable();
            $table->longText('body')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_log');
    }
};
