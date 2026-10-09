<?php

namespace Tests\Feature;

use App\Models\Plot;
use App\Models\Registration;
use App\Models\RegistrationParty;
use App\Services\SalesService;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_full_registration_flow_to_sold(): void
    {
        Mail::fake();
        [$tenant, $admin] = $this->makeTenant('Reg Co', true);
        $p = LaunchTest::readyProject($tenant);
        $p->update(['status' => 'launched', 'launched_at' => now(), 'district' => 'Thanjavur', 'location' => 'Vallam', 'survey_numbers' => '45/2']);
        $plot = app(Tenancy::class)->run($tenant, fn () => Plot::create(['tenant_id' => $tenant->id, 'project_id' => $p->id, 'plot_no' => '7', 'patta_number' => '777', 'size_sqft' => 1000, 'facing' => 'East', 'rate_per_sqft' => 1000]));
        $sales = $this->makeUser($tenant, 'tenant_sales');
        $c = $this->makeCustomer();
        $c->associateWith($tenant->id);
        $sale = SalesService::initiateSale($plot, $c, ['amount' => 1000000, 'mode' => 'bank_transfer', 'paid_on' => today()->toDateString()], null, null, null, $sales->id);
        $this->assertSame('ror', $sale->status);

        $form = $this->actingAs($sales)->get(route('ws.registrations.create', $sale))->assertOk();
        $this->assertSame('SRO Vallam', $form->viewData('sro')->name);
        $sroId = $form->viewData('sro')->id;
        $payload = [
            'registration_date' => today()->addDays(2)->toDateString(), 'sro_id' => $sroId, 'pr_numbers' => 'PR-1',
            'seller_name' => $tenant->name, 'seller_address' => 'Thanjavur', 'seller_pan' => 'ABCDE1234F',
            'buyer_name' => $c->name, 'buyer_address' => 'Trichy', 'buyer_pan' => 'pqrst6789z',
            'w' => [['name' => 'W One', 'address' => 'A'], ['name' => 'W Two', 'address' => 'B']],
            'east_boundary' => '30 ft road', 'checklist' => [],
        ];
        $this->actingAs($sales)->post(route('ws.registrations.store', $sale), $payload)->assertSessionHasErrors('disclaimer');
        $this->actingAs($sales)->post(route('ws.registrations.store', $sale), $payload + ['disclaimer' => '1'])->assertRedirect();
        $reg = Registration::withoutGlobalScopes()->first();
        $this->assertSame('30 ft road', $plot->fresh()->east_boundary);

        // PAN stored encrypted, masked for non-admin, full for admin
        $buyer = RegistrationParty::withoutGlobalScopes()->where('party_type', 'buyer')->first();
        $this->assertStringNotContainsString('PQRST', (string) \DB::table('registration_parties')->where('id', $buyer->id)->value('pan_encrypted'));
        $this->assertSame('PQRST****Z', $buyer->panFor($sales));
        $this->assertSame('PQRST6789Z', $buyer->panFor($admin));
        $this->actingAs($sales)->get(route('ws.registrations.show', $reg))->assertOk()->assertSee('PQRST****Z')->assertDontSee('PQRST6789Z');

        // saving with the masked PAN keeps the stored value
        $this->actingAs($sales)->put(route('ws.registrations.update', $reg), array_merge($payload, ['buyer_pan' => 'PQRST****Z']))->assertSessionHas('ok');
        $this->assertSame('PQRST6789Z', RegistrationParty::withoutGlobalScopes()->where('party_type', 'buyer')->first()->pan());

        // submit needs checklist + documents
        $this->actingAs($sales)->post(route('ws.registrations.submit', $reg))->assertSessionHas('error');
        $codes = collect($reg->fresh()->checklist)->where('mandatory', true)->pluck('code')->all();
        $this->actingAs($sales)->put(route('ws.registrations.update', $reg), ['checklist_only' => 1, 'checklist' => $codes])->assertSessionHas('ok');
        $this->actingAs($sales)->post(route('ws.registrations.submit', $reg))->assertSessionHas('error');
        $this->actingAs($sales)->post(route('ws.registrations.upload', $reg), ['file' => UploadedFile::fake()->createWithContent('deed.pdf', "%PDF-1.4\n".str_repeat('y', 500))])->assertSessionHas('ok');
        $this->actingAs($sales)->get(route('ws.registrations.pack', $reg))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($sales)->post(route('ws.registrations.submit', $reg))->assertSessionHas('ok');
        $this->assertSame('ror_init', $plot->fresh()->status);

        $this->actingAs($sales)->post(route('ws.registrations.complete', $reg), ['registered_doc_no' => '1234/2026', 'registered_doc_date' => today()->toDateString()])->assertSessionHas('ok');
        $this->assertSame('ror_completed', $plot->fresh()->status);
        $this->actingAs($sales)->get(route('ws.registrations.ack', $reg))->assertOk();
        $this->actingAs($sales)->post(route('ws.registrations.sold', $reg), ['physical_file_no' => 'F-22'])->assertSessionHas('ok');
        $this->assertSame('sold', $plot->fresh()->status);
        $this->assertSame('completed', $sale->fresh()->status);
        Mail::assertQueued(\App\Mail\TemplateMail::class, fn ($m) => str_contains($m->mailSubject, 'Registration update'));

        // the buyer sees the shared document; the sold plot leaves the showcase
        $file = $reg->files()->first();
        $this->actingAs($c, 'customer')->get(route('account.documents'))->assertOk()->assertSee('deed.pdf');
        $this->actingAs($c, 'customer')->get(route('account.file', $file))->assertOk();
        $this->actingAs($this->makeCustomer(), 'customer')->get(route('account.file', $file))->assertNotFound();
        auth('customer')->logout();
        $this->get($p->publicUrl())->assertOk()->assertDontSee('data-no="7"', false);
        $this->actingAs($admin)->get(route('ws.registrations.index'))->assertOk();
    }
}
