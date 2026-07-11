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

// ── slug ─────────────────────────────────────────────────────────────────────

it('SlugField builds a unique string column', function () {
    $field = new SlugField;

    expect($field->columnDefinition('slug', []))->toBe("\$table->string('slug')->unique();")
        ->and($field->rules('slug', []))->toBe(['string', 'alpha_dash'])
        ->and($field->cast([]))->toBeNull();
});
