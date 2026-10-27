<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/ai', static function (Router $router): void {
        $router->get('/assistant', static function (Request $request) {
            $db = db();
            // La clé API chiffrée n'est jamais lue ni affichée.
            $providers = array_map(
                static fn (array $r): array => $r + ['enabled' => (int) $r['is_enabled'] === 1 ? 'Activé' : 'Désactivé'],
                $db->fetchAll('SELECT provider, is_enabled, model, url FROM ai_provider_settings ORDER BY provider')
            );
            $conversations = $db->fetchAll(
                'SELECT title, provider, created_at FROM ai_conversations WHERE user_id = :u ORDER BY created_at DESC LIMIT 20',
                ['u' => auth()->id()]
            );
            return view('modules/list', [
                'title' => 'Assistant IA',
                'heading' => 'Assistant IA',
                'intro' => 'L\'application reste pleinement fonctionnelle sans fournisseur IA.',
                'notice' => 'L\'interface de conversation n\'est pas encore implémentée ; seul l\'état des fournisseurs est affiché.',
                'sections' => [
                    ['title' => 'Fournisseurs', 'rows' => $providers, 'empty' => 'Aucun fournisseur configuré.', 'columns' => [
                        'provider' => ['Fournisseur', 'badge'], 'enabled' => ['État'], 'model' => ['Modèle'], 'url' => ['URL'],
                    ]],
                    ['title' => 'Mes conversations', 'rows' => $conversations, 'empty' => 'Aucune conversation.', 'columns' => [
                        'title' => ['Titre'], 'provider' => ['Fournisseur', 'badge'], 'created_at' => ['Date', 'datetime'],
                    ]],
                ],
            ]);
        })->middleware('permission:ai.use')->name('ai.assistant');
    });
};
