<?php

use Baobab\Mail\CssInliner;

it('inlines a <style> block\'s rules into the matching HTML elements', function () {
    $html = (new CssInliner)->inline(
        '<html><head><style>.lead { color: red; }</style></head><body><p class="lead">Bonjour</p></body></html>',
    );

    expect($html)->toContain('style="color: red;"');
});
