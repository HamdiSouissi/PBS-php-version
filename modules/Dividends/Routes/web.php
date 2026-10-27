<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/dividends', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $db = db();
            $name = static fn (array $r): string => $r['entity_name'] !== ''
                ? (string) $r['entity_name']
                : trim($r['first_name'] . ' ' . mb_strtoupper((string) $r['last_name']));
            $shareholders = array_map(
                static fn (array $r): array => $r + ['name' => $name($r)],
                $db->fetchAll('SELECT * FROM shareholders ORDER BY share_pct DESC')
            );
            $dividends = array_map(
                static fn (array $r): array => $r + ['name' => $name($r)],
                $db->fetchAll(
                    'SELECT d.*, s.entity_name, s.first_name, s.last_name
                     FROM dividends d JOIN shareholders s ON s.id = d.shareholder_id
                     ORDER BY d.decided_on DESC LIMIT 100'
                )
            );
            return view('modules/list', [
                'title' => 'Associés & dividendes',
                'heading' => 'Associés & dividendes',
                'intro' => 'Table de capital et dividendes décidés.',
                'sections' => [
                    ['title' => 'Associés', 'rows' => $shareholders, 'empty' => 'Aucun associé.', 'columns' => [
                        'name' => ['Associé'], 'type' => ['Type', 'badge'], 'shares_count' => ['Parts'], 'share_pct' => ['Détention %'], 'entry_date' => ['Entrée', 'date'],
                    ]],
                    ['title' => 'Dividendes', 'rows' => $dividends, 'empty' => 'Aucun dividende.', 'columns' => [
                        'decided_on' => ['Décidé le', 'date'], 'name' => ['Associé'], 'gross_amount' => ['Brut', 'money'], 'net_amount' => ['Net', 'money'], 'status' => ['Statut', 'badge'],
                    ]],
                ],
            ]);
        })->middleware('permission:dividends.read')->name('dividends.index');
    });
};
