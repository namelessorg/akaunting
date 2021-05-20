<?php

namespace App\Models\Common;

use App\Abstracts\Model;
use App\Models\Document\Document;
use App\Scopes\Contact as Scope;
use App\Traits\Contacts;
use App\Traits\Currencies;
use App\Traits\Media;
use App\Traits\Transactions;
use Bkwld\Cloner\Cloneable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

/**
 * Class Contact
 *
 * @package App\Models\Common
 * @property int $id
 * @property CarbonInterface $expires_at
 * @property bool $enabled
 * @property Company $company
 * @property string $telegram_id
 * @property string $name
 * @property string $currency_code
 * @property array $mt
 * @property array $last_command
 * @property int $telegram_chat_id
 * @method Builder activeContacts
 */
class Contact extends Model
{
    use Cloneable, Contacts, Currencies, HasFactory, Media, Notifiable, Transactions;

    protected $table = 'contacts';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['company_id', 'type', 'name', 'email', 'user_id', 'tax_number', 'phone', 'address', 'website', 'currency_code', 'reference', 'enabled', 'expires_at', 'telegram_id', 'telegram_chat_id', 'mt', 'last_command'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'enabled' => 'boolean',
        'expires_at' => 'datetime',
        'mt' => 'array',
        'last_command' => 'array'
    ];

    /**
     * Sortable columns.
     *
     * @var array
     */
    public $sortable = ['name', 'email', 'phone', 'enabled'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        parent::booted();

        static::addGlobalScope(new Scope);
    }

    public function documents()
    {
        return $this->hasMany('App\Models\Document\Document');
    }

    public function bills()
    {
        return $this->documents()->where('type', Document::BILL_TYPE);
    }

    public function currency()
    {
        return $this->belongsTo('App\Models\Setting\Currency', 'currency_code', 'code');
    }

    public function expense_transactions()
    {
        return $this->transactions()->whereIn('type', (array)$this->getExpenseTypes());
    }

    public function income_transactions()
    {
        return $this->transactions()->whereIn('type', (array)$this->getIncomeTypes());
    }

    public function invoices()
    {
        return $this->documents()->where('type', Document::INVOICE_TYPE);
    }

    public function transactions()
    {
        return $this->hasMany('App\Models\Banking\Transaction');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\Auth\User', 'user_id', 'id');
    }

    /**
     * Scope to only include contacts of a given type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed                                 $types
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeType($query, $types)
    {
        if (empty($types)) {
            return $query;
        }

        return $query->whereIn($this->table . '.type', (array)$types);
    }

    /**
     * Scope to include only vendors.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVendor($query)
    {
        return $query->whereIn($this->table . '.type', (array)$this->getVendorTypes());
    }

    /**
     * Scope to include only customers.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCustomer($query)
    {
        return $query->whereIn($this->table . '.type', (array)$this->getCustomerTypes());
    }

    public function scopeEmail($query, $email)
    {
        return $query->where('email', '=', $email);
    }

    public function scopeActiveContacts(Builder $query)
    {
        return $query->whereNested(static function (\Illuminate\Database\Query\Builder $query) {
            $query->orWhere('expires_at', '>', now());
            $query->orWhereNull('expires_at');
        })->where('enabled', 1)->whereNotNull('company_id');
    }

    public function onCloning($src, $child = null)
    {
        $this->email = null;
        $this->user_id = null;
    }

    public function setMtAttribute($value)
    {
        $this->attributes['mt'] = $this->asJson(array_filter(array_unique(preg_split('/\D/', $value))));
    }

    public function getMtStringAttribute()
    {
        return implode(PHP_EOL, $this->fromJson($this->attributes['mt'] ?? '{}'));
    }

    /**
     * Get the current balance.
     *
     * @return string
     */
    public function getLogoAttribute($value)
    {
        if (!empty($value) && !$this->hasMedia('logo')) {
            return $value;
        } elseif (!$this->hasMedia('logo')) {
            return false;
        }

        return $this->getMedia('logo')->last();
    }

    public function getUnpaidAttribute()
    {
        $amount = 0;

        $collection = $this->isCustomer() ? 'invoices' : 'bills';

        $this->$collection->whereNotIn('status', ['draft', 'cancelled', 'paid'])->each(function ($item) use (&$amount) {
            $unpaid = $item->amount - $item->paid;

            $amount += $this->convertToDefault($unpaid, $item->currency_code, $item->currency_rate);
        });

        return $amount;
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Contact::new();
    }
}
