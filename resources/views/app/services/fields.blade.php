<x-field name="name" label="Name" :value="$s->name" :id="$p.'_name'" required />
<x-select name="available_to" label="Available to" :options="['both' => 'Promoters & buyers', 'tenant' => 'Promoters only', 'customer' => 'Buyers only']" :value="$s->available_to" :id="$p.'_to'" />
<x-field name="summary" label="Short summary" :value="$s->summary" :id="$p.'_sum'" class="sm:col-span-2" />
<x-textarea name="description" label="Description" :value="$s->description" :id="$p.'_desc'" rows="3" class="sm:col-span-2" />
<x-field name="price" type="number" step="0.01" min="0" label="Price (₹)" :value="$s->price" :id="$p.'_price'" hint="Leave blank and tick “On request” to hide the price." />
<x-field name="sort" type="number" min="0" label="Order" :value="$s->sort" :id="$p.'_sort'" />
<input type="hidden" name="price_on_request" value="0"><x-checkbox name="price_on_request" label="Price on request" :checked="$s->price_on_request" />
<input type="hidden" name="is_active" value="0"><x-checkbox name="is_active" label="Active (shown on the marketplace)" :checked="$s->is_active" />
