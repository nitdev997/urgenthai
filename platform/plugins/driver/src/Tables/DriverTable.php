<?php

namespace Botble\Driver\Tables;

use Botble\Driver\Models\Driver;
use Botble\Ecommerce\Models\Address;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\BulkChanges\CreatedAtBulkChange;
use Botble\Table\BulkChanges\NameBulkChange;
use Botble\Table\BulkChanges\StatusBulkChange;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\NameColumn;
use Botble\Table\Columns\DocumentVerificationStatusColumn;
use Botble\Table\Columns\EmailColumn;
use Botble\Table\Columns\PhoneColumn;
use Botble\Table\Columns\AddressColumn;
use Botble\Table\Columns\DLNumberColumn;
use Botble\Table\Columns\CountryColumn;
use Botble\Table\Columns\CountryColumn as RatingColumn;
use Botble\Table\Columns\CountryColumn as EarningsColumn;
use Botble\Table\Columns\CountryColumn as WithdrawalsColumn;
use Botble\Table\BulkChanges\EmailBulkChange;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Illuminate\Database\Eloquent\Builder;

class DriverTable extends TableAbstract
{
    public function setup(): void
    {
        $this
            ->model(Driver::class)
            // ->addHeaderAction(CreateHeaderAction::make()->route('driver.create'))
            ->addActions([
                EditAction::make()->route('driver.edit'),
                DeleteAction::make()->route('driver.destroy'),
            ])
            ->addColumns([
                IdColumn::make(),
                NameColumn::make()->route('driver.edit'),
                EmailColumn::make(),
                PhoneColumn::make(),
                RatingColumn::make('rating')->title('rating'),
                EarningsColumn::make('earnings')->title('earnings'),
                WithdrawalsColumn::make('withdrawals')->title('withdrawals'),
                AddressColumn::make(),
                DLNumberColumn::make(),
                CountryColumn::make(),
                DocumentVerificationStatusColumn::make(),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('driver.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make(),
                StatusBulkChange::make(),
                EmailBulkChange::make(),
            ])
            ->queryUsing(function (Builder $query) {
                $query->select([
                    'id',
                    'name',
                    'email',
                    'phone',
                    'rating',
                    'earnings',
                    'withdrawals',
                    'address',
                    'dl_number',
                    'country',
                    'document_verification_status',
                ]);
            });
    }
}
