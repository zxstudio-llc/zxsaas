<?php

namespace App\Filament\Forms\Components;

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentType;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;

class DocumentTotals extends Grid
{
    protected string $view = 'filament.forms.components.document-totals';

    protected DocumentType $documentType = DocumentType::Invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema([
            TextInput::make('discount_rate')
                ->label('Discount Rate')
                ->hiddenLabel()
                ->live()
                ->rate(computation: static fn (Get $get) => $get('discount_computation'), showAffix: false),
            Select::make('discount_computation')
                ->label('Discount Computation')
                ->hiddenLabel()
                ->options([
                    'percentage' => '%',
                    'fixed' => '$',
                ])
                ->default(AdjustmentComputation::Percentage)
                ->selectablePlaceholder(false)
                ->live(),
        ]);
    }

    public function type(DocumentType | string $type): static
    {
        if (is_string($type)) {
            $type = DocumentType::from($type);
        }

        $this->documentType = $type;

        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->documentType;
    }
}
