<?php

declare(strict_types=1);

namespace Baobab\Themes\Validation;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Détecte les motifs PHP interdits dans un thème (spec 03 §10.3, niveau 2) —
 * un seul visiteur, pas un par motif : les catégories partagent la même
 * mécanique (repérer un appel/une classe par son nom écrit dans le source).
 * Limite assumée, comme le rappelle la spec elle-même : « l'analyse
 * statique n'est pas une sandbox d'exécution » — un alias d'import
 * (`use ...Facades\DB as Db;`) échappe à la détection par nom littéral,
 * pas de résolution de noms complète dans ce premier passage.
 */
final class ForbiddenConstructVisitor extends NodeVisitorAbstract
{
    /** @var list<ThemeViolation> */
    private array $violations = [];

    public function __construct(
        private readonly string $file,
        private readonly string $themeTablePrefix,
    ) {}

    /**
     * @return list<ThemeViolation>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    public function enterNode(Node $node): null
    {
        match (true) {
            $node instanceof Node\Expr\ShellExec => $this->violate($node, 'Exécution via l\'opérateur backticks interdite dans un thème.'),
            // eval() est un language construct, pas un appel de fonction —
            // nikic/php-parser le représente par son propre type de nœud,
            // il n'apparaît jamais comme Expr\FuncCall.
            $node instanceof Node\Expr\Eval_ => $this->violate($node, 'Exécution de code interdite dans un thème : eval().'),
            $node instanceof Node\Expr\FuncCall => $this->checkFuncCall($node),
            $node instanceof Node\Expr\StaticCall => $this->checkStaticCall($node),
            $node instanceof Node\Expr\New_ => $this->checkNew($node),
            $node instanceof Node\Expr\MethodCall => $this->checkMethodCall($node),
            $node instanceof Node\Stmt\Class_ => $this->checkClass($node),
            default => null,
        };

        return null;
    }

    private function checkFuncCall(Node\Expr\FuncCall $node): void
    {
        if (! $node->name instanceof Node\Name) {
            return;
        }

        $name = strtolower($node->name->toString());

        if (in_array($name, ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'assert'], true)) {
            $this->violate($node, "Exécution de code interdite dans un thème : {$name}().");

            return;
        }

        if (str_starts_with($name, 'curl_') || $name === 'fsockopen') {
            $this->violate($node, "Appel réseau sortant interdit dans un thème : {$name}().");

            return;
        }

        if ($name === 'file_get_contents' && $this->firstArgIsUrl($node->args)) {
            $this->violate($node, 'file_get_contents() sur une URL est interdit dans un thème (appel réseau sortant).');

            return;
        }

        if (in_array($name, ['file_put_contents', 'unlink', 'rename', 'mkdir', 'rmdir', 'copy'], true)) {
            $this->violate($node, "Accès fichier bas niveau interdit dans un thème : {$name}().");

            return;
        }

        if ($name === 'fopen' && $this->fopenIsWriteMode($node->args)) {
            $this->violate($node, 'fopen() en mode écriture est interdit dans un thème.');
        }
    }

    private function checkStaticCall(Node\Expr\StaticCall $node): void
    {
        if (! $node->class instanceof Node\Name) {
            return;
        }

        $class = $node->class->toString();
        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (in_array($class, ['DB', 'Illuminate\Support\Facades\DB'], true)) {
            $this->violate($node, 'Accès base de données bas niveau interdit dans un thème : DB::.');

            return;
        }

        if (in_array($class, ['Http', 'Illuminate\Support\Facades\Http'], true)) {
            $this->violate($node, 'Appel HTTP sortant interdit dans un thème : Http::.');

            return;
        }

        if (in_array($class, ['Route', 'Illuminate\Support\Facades\Route'], true)) {
            $this->violate($node, 'Déclaration de route interdite dans un thème : Route::.');

            return;
        }

        if (in_array($class, ['Gate', 'Illuminate\Support\Facades\Gate'], true) && in_array($method, ['policy', 'define'], true)) {
            $this->violate($node, "Déclaration de policy/gate interdite dans un thème : Gate::{$method}().");
        }
    }

    private function checkNew(Node\Expr\New_ $node): void
    {
        if (! $node->class instanceof Node\Name) {
            return;
        }

        $class = $node->class->toString();

        if ($class === 'PDO') {
            $this->violate($node, 'Accès base de données bas niveau interdit dans un thème : new PDO().');

            return;
        }

        if (str_contains($class, 'Guzzle')) {
            $this->violate($node, 'Client HTTP interdit dans un thème (Guzzle).');
        }
    }

    private function checkMethodCall(Node\Expr\MethodCall $node): void
    {
        $method = $node->name instanceof Node\Identifier ? $node->name->toString() : null;

        if (! in_array($method, ['bind', 'singleton', 'extend'], true)) {
            return;
        }

        if ($this->looksLikeAppContainer($node->var)) {
            $this->violate($node, "Liaison de service Core interdite dans un thème : ->{$method}().");
        }
    }

    private function looksLikeAppContainer(Node\Expr $expr): bool
    {
        if ($expr instanceof Node\Expr\PropertyFetch && $expr->name instanceof Node\Identifier && $expr->name->toString() === 'app') {
            return true;
        }

        return $expr instanceof Node\Expr\FuncCall && $expr->name instanceof Node\Name && $expr->name->toString() === 'app';
    }

    /**
     * Modèle Eloquent référençant une table sans le préfixe du thème (spec
     * 03 §10.3). Vérifiable seulement quand `$table` est un littéral
     * explicite — une classe qui laisse Eloquent inférer le nom par
     * convention n'est pas vérifiable statiquement, limite documentée.
     */
    private function checkClass(Node\Stmt\Class_ $node): void
    {
        if ($node->extends === null || ! str_contains($node->extends->toString(), 'Model')) {
            return;
        }

        $table = $this->tableProperty($node);

        if ($table !== null && ! str_starts_with($table, "{$this->themeTablePrefix}_")) {
            $this->violate($node, "Modèle Eloquent référençant une table hors du préfixe du thème ({$this->themeTablePrefix}_) : {$table}.");
        }
    }

    private function tableProperty(Node\Stmt\Class_ $class): ?string
    {
        foreach ($class->stmts as $stmt) {
            if (! $stmt instanceof Node\Stmt\Property) {
                continue;
            }

            foreach ($stmt->props as $prop) {
                if ($prop->name->toString() === 'table' && $prop->default instanceof Node\Scalar\String_) {
                    return $prop->default->value;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<Node\Arg|Node\VariadicPlaceholder>  $args
     */
    private function firstArgIsUrl(array $args): bool
    {
        if (! isset($args[0]) || ! $args[0] instanceof Node\Arg || ! $args[0]->value instanceof Node\Scalar\String_) {
            return false;
        }

        $value = $args[0]->value->value;

        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * @param  list<Node\Arg|Node\VariadicPlaceholder>  $args
     */
    private function fopenIsWriteMode(array $args): bool
    {
        if (! isset($args[1]) || ! $args[1] instanceof Node\Arg || ! $args[1]->value instanceof Node\Scalar\String_) {
            return false;
        }

        return preg_match('/[wax+]/', $args[1]->value->value) === 1;
    }

    private function violate(Node $node, string $message): void
    {
        $this->violations[] = new ThemeViolation($this->file, $node->getLine(), $message, blocking: true);
    }
}
