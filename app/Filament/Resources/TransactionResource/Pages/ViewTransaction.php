<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Infolists\Infolist; // Masukan import Infolist
use Filament\Infolists\Components\Section; // Menggantikan Card di Filament V3
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    // Fungsi infolist HARUS berada di dalam class
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([

                // General Information
                Section::make('General Information') // Menggunakan Section
                    ->schema([
                        TextEntry::make('invoice')->label('Invoice'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'success' => 'success',
                                'expired' => 'gray',
                                'failed' => 'danger',
                                default => 'gray', // Berikan default jika status lain
                            }),
                        TextEntry::make('created_at')->label('Created At')->dateTime(),
                    ])->columns(3),

                // Customer Information
                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer.name')->label('Customer Name'),
                        TextEntry::make('customer.email')->label('Email Address'),
                        TextEntry::make('address')->label('Address'),
                    ])->columns(3),

                // Ongkos Kirim
                Section::make('Shipping Information')
                    ->schema([
                        TextEntry::make('shipping.shipping_courier')->label('Jasa Kirim'),
                        TextEntry::make('shipping.shipping_service')->label('Layanan Kirim'),
                        TextEntry::make('shipping.shipping_cost')->label('Ongkos Kirim')->numeric(0)->money('IDR', locale: 'id'),
                    ])->columns(3),

                // Transaction Details
                Section::make('Items Details')
                    ->schema([
                        RepeatableEntry::make('TransactionDetails') // Pastikan nama relasi di Model sudah benar (biasanya pakai camelCase / snake_case)
                            ->label('')
                            ->schema([
                                ImageEntry::make('product.image')->label('Product Image')->circular()->width(100)->height(100),
                                TextEntry::make('product.title')->label('Product Name'),
                                TextEntry::make('qty')->label('Quantity'),
                                TextEntry::make('price')->label('Price')->numeric(0)->money('IDR', locale: 'id'),
                            ])
                            ->columns(4),
                    ]),

                // Grand Total Section
                Section::make()
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('total')
                                    ->label('Grand Total')
                                    ->numeric(0)
                                    ->money('IDR', locale: 'id')
                                    ->weight('bold') // Membuat teks tebal
                                    ->color('primary')
                                    ->alignEnd() // Mengarahkan teks ke kanan
                            ])
                    ])
            ]);
    }
}
