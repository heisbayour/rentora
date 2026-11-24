<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExpenseRequest;
use App\Models\Expense;
use App\Models\Property;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Support\Money;
use App\Support\PrivateFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Expenses per property or unit with a receipt upload (spec 8.9). Owners and managers. */
class ExpenseController extends Controller
{
    public function __construct(
        private readonly PrivateFiles $files,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');
        $propertyId = $request->integer('property_id') ?: null;

        $query = Expense::query()->with(['property', 'unit'])
            ->when($search !== '', fn (Builder $q) => $q->where('description', 'like', "%{$search}%"))
            ->when(in_array($category, ExpenseCategory::values(), true), fn (Builder $q) => $q->where('category', $category))
            ->when($propertyId !== null, fn (Builder $q) => $q->where('property_id', $propertyId));

        return view('admin.expenses.index', [
            'expenses' => (clone $query)->latest('incurred_on')->latest('id')->paginate((int) config('rentora.per_page'))->withQueryString(),
            'total' => Money::forOrganization((int) (clone $query)->sum('amount')),
            'categories' => ExpenseCategory::options(),
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id'),
            'search' => $search,
            'category' => $category,
            'propertyId' => $propertyId,
        ]);
    }

    public function create(): View
    {
        return view('admin.expenses.form', $this->formData(new Expense(['incurred_on' => today(), 'category' => ExpenseCategory::Repairs])));
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $expense = new Expense($this->attributes($request));
        $expense->recorded_by = $request->user()->id;
        $expense->save();
        $this->logger->log('expense.created', $expense, ['amount' => $expense->amount]);

        return redirect()->route('app.expenses.index')->with('toast', __('expenses.saved'));
    }

    public function edit(Expense $expense): View
    {
        return view('admin.expenses.form', $this->formData($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $old = $expense->receipt_path;
        $expense->fill($this->attributes($request))->save();
        if ($request->hasFile('receipt') && $old !== null) {
            $this->files->delete($old);
        }
        $this->logger->log('expense.updated', $expense, ['amount' => $expense->amount]);

        return redirect()->route('app.expenses.index')->with('toast', __('expenses.saved'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->files->delete($expense->receipt_path);
        $expense->delete();
        $this->logger->log('expense.deleted', null, ['id' => $expense->id, 'amount' => $expense->amount]);

        return redirect()->route('app.expenses.index')->with('toast', __('expenses.deleted'));
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        $disk = Storage::disk(PrivateFiles::DISK);
        abort_unless($expense->receipt_path !== null && $disk->exists($expense->receipt_path), 404);

        return $disk->download($expense->receipt_path, 'expense-'.$expense->id.'.'.pathinfo($expense->receipt_path, PATHINFO_EXTENSION), [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(ExpenseRequest $request): array
    {
        $attributes = $request->safe()->except('receipt');
        if ($request->hasFile('receipt')) {
            $attributes['receipt_path'] = $this->files->store($request->file('receipt'), 'expenses');
        }

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function formData(Expense $expense): array
    {
        return [
            'expense' => $expense,
            'categories' => ExpenseCategory::options(),
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id'),
            'units' => Unit::query()->with('property')->orderBy('property_id')->orderBy('label')->get()
                ->mapWithKeys(fn (Unit $unit) => [$unit->id => $unit->displayName()]),
            'currencySymbol' => Money::forOrganization(0)->symbol(),
        ];
    }
}
