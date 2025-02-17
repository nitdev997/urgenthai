<?php

namespace Botble\Commission\Tables;

use Botble\Commission\Models\Commission;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\EditAction;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\Column;
use Illuminate\Database\Eloquent\Builder;

class CommissionTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Commission::class)
            ->addActions([
                EditAction::make()->route('commission.edit'),
            ])
            ->addColumns([
                IdColumn::make(),
                Column::make('driver')->title('driver (%)'),
                Column::make('vendor')->title('vendor (%)')
            ])
            ->queryUsing(function (Builder $query) {
                $query->select([
                    'id',
                    'driver',
                    'vendor',
                ]);
            });
    }
}
