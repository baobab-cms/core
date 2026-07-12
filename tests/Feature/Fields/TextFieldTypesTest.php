<?php

use Baobab\ContentTypes\Fields\Types\RichTextField;
use Baobab\ContentTypes\Fields\Types\SlugField;
use Baobab\ContentTypes\Fields\Types\TextareaField;
use Baobab\ContentTypes\Fields\Types\TextField;
use Illuminate\Database\Eloquent\Model;
use Mews\Purifier\Casts\CleanHtml;

// ── text ─────────────────────────────────────────────────────────────────────

it('TextField builds a string column with the default max length', function () {
    $field = new TextField;

    expect($field->columnDefinition('title', []))->toBe("\$table->string('title', 255);")
        ->and($field->rules('title', []))->toBe(['string', 'max:255'])
        ->and($field->cast([]))->toBeNull()
        ->and($field->graphqlType([]))->toBe('String');
});

it('TextField honors a declared max_length option', function () {
    $field = new TextField;

    expect($field->columnDefinition('title', ['max_length' => 64]))->toBe("\$table->string('title', 64);")
        ->and($field->rules('title', ['max_length' => 64]))->toBe(['string', 'max:64']);
});

// ── textarea ─────────────────────────────────────────────────────────────────

it('TextareaField builds a text column', function () {
    $field = new TextareaField;

    expect($field->columnDefinition('summary', []))->toBe("\$table->text('summary');")
        ->and($field->rules('summary', []))->toBe(['string'])
        ->and($field->cast([]))->toBeNull();
});

// ── richtext ─────────────────────────────────────────────────────────────────

it('RichTextField builds a longtext column and casts through mews/purifier', function () {
    $field = new RichTextField;

    expect($field->columnDefinition('body', []))->toBe("\$table->longText('body');")
        ->and($field->cast([]))->toBe(CleanHtml::class);
});

it('RichTextField cast strips disallowed tags and attributes on save', function () {
    $model = new class extends Model
    {
        protected $table = 'ct_cars';

        protected $fillable = ['body'];

        protected function casts(): array
        {
            return ['body' => CleanHtml::class];
        }
    };

    $model->setAttribute('body', '<p onclick="alert(1)">Hello</p><script>alert(1)</script>');

    $cleaned = (string) $model->getAttribute('body');

    expect($cleaned)->not->toContain('<script>');
    expect($cleaned)->not->toContain('onclick');
});

it('RichTextField cast keeps every tag the Tiptap toolbar produces (M4 point 4a)', function () {
    $model = new class extends Model
    {
        protected $table = 'ct_cars';

        protected $fillable = ['body'];

        protected function casts(): array
        {
            return ['body' => CleanHtml::class];
        }
    };

    $model->setAttribute('body', implode('', [
        '<h2>Titre</h2><h3>Sous-titre</h3><h4>Sous-sous-titre</h4>',
        '<p style="text-align: center">Texte <u>souligné</u> et <s>barré</s> et <mark>surligné</mark>.</p>',
        '<blockquote>Citation</blockquote>',
        '<ul><li>Un</li></ul><ol><li>Deux</li></ol>',
        '<p><a href="https://example.com" title="Exemple">lien</a></p>',
        // Tiptap insère toujours <hr> comme sibling de niveau bloc, jamais collé à du
        // texte nu — un <hr> non encadré par des blocs se fait fusionner/supprimer par
        // l'auto-paragraphe de HTMLPurifier, ce n'est pas un cas réaliste à couvrir ici.
        '<hr>',
        '<p>Fin.</p>',
    ]));

    $cleaned = (string) $model->getAttribute('body');

    expect($cleaned)->toContain('<h2>')
        ->toContain('<h3>')
        ->toContain('<h4>')
        ->toContain('<u>')
        ->toContain('<s>')
        ->toContain('<mark>')
        ->toContain('<blockquote>')
        ->toContain('<ul>')
        ->toContain('<ol>')
        ->toContain('href="https://example.com"')
        ->toContain('<hr')
        ->toContain('text-align');
});

// ── slug ─────────────────────────────────────────────────────────────────────

it('SlugField builds a unique string column', function () {
    $field = new SlugField;

    expect($field->columnDefinition('slug', []))->toBe("\$table->string('slug')->unique();")
        ->and($field->rules('slug', []))->toBe(['string', 'alpha_dash'])
        ->and($field->cast([]))->toBeNull();
});
