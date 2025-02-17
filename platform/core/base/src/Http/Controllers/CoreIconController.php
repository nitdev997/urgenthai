<?php

namespace Botble\Base\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CoreIconController extends BaseController
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
        ]);

        $icons = Cache::remember('core-icons', 60 * 60 * 24 * 30, function () {
            $files = File::glob(core_path('base/resources/views/components/icons/*.blade.php'));

            $icons = [];

            foreach ($files as $file) {
                $content = File::get($file);
                $name = 'ti ti-' . str_replace('.blade.php', '', basename($file));

                $icons[$name] = $content;
            }

            return $icons;
        });

        $currentPage = Paginator::resolveCurrentPage();
        $perPage = $request->integer('per_page', 100);
        $collection = new Collection($icons);

        $icons = $collection
            ->when($request->query('q'), function (Collection $collection, $search) {
                return $collection->filter(fn ($item) => str_contains($item, $search));
            })
            ->slice(($currentPage - 1) * $perPage, $perPage)->all();

        return (new LengthAwarePaginator($icons, count($collection), $perPage))
            ->setPath(route('core-icons'))
            ->appends($request->except('page'));
    }
}
