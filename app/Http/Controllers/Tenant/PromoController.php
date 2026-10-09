<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PromoCode;
use App\Models\SocialPost;
use App\Services\SocialService;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Social posts with promo codes for launched projects (Admin / Manager). Customers enter the code when booking. */
class PromoController extends Controller
{
    public function index()
    {
        return view('ws.promos.index', [
            'posts' => SocialPost::with('project', 'promoCode')->latest('id')->paginate(15),
            'codes' => PromoCode::withCount('uses')->latest('id')->get(),
            'projects' => Project::launched()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|integer',
            'platform' => ['required', Rule::in(array_keys(SocialService::PLATFORMS))],
            'brief' => 'nullable|string|max:500',
            'promo_code_id' => 'nullable|integer',
        ]);
        $project = Project::launched()->with('plots')->findOrFail($data['project_id']);
        $promo = ! empty($data['promo_code_id']) ? PromoCode::findOrFail($data['promo_code_id']) : null;
        try {
            $text = SocialService::write($project, $data['platform'], $promo, app(Tenancy::class)->get(), (string) ($data['brief'] ?? ''));
        } catch (\App\Exceptions\PlanLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        $post = SocialPost::create($text + ['tenant_id' => app(Tenancy::class)->id(), 'project_id' => $project->id, 'platform' => $data['platform'], 'status' => 'draft', 'created_by' => $request->user()->id]);
        if ($promo) {
            $promo->update(['social_post_id' => $post->id]);
        }

        return back()->with('ok', 'Post drafted. Edit it, then copy it to '.SocialService::PLATFORMS[$post->platform].'.');
    }

    public function updatePost(Request $request, SocialPost $post)
    {
        $post->update($request->validate(['caption' => 'required|string|max:5000', 'hashtags' => 'nullable|string|max:500']));

        return back()->with('ok', 'Post saved.');
    }

    public function destroyPost(SocialPost $post)
    {
        $post->delete();

        return back()->with('ok', 'Post deleted.');
    }

    public function storeCode(Request $request)
    {
        PromoCode::create($this->validated($request) + ['tenant_id' => app(Tenancy::class)->id(), 'created_by' => $request->user()->id]);

        return back()->with('ok', 'Promo code created.');
    }

    public function updateCode(Request $request, PromoCode $code)
    {
        $code->update($this->validated($request, $code) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('ok', 'Promo code updated.');
    }

    private function validated(Request $request, ?PromoCode $code = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('promo_codes', 'code')->where('tenant_id', app(Tenancy::class)->id())->ignore($code?->id)],
            'discount_type' => 'required|in:percent,flat',
            'value' => 'required|numeric|min:0.01',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:0',
            'project_ids' => 'array',
            'project_ids.*' => 'integer',
        ]);
        if ($data['discount_type'] === 'percent' && $data['value'] > 100) {
            abort(422, 'A percentage discount cannot exceed 100%.');
        }
        $data['code'] = strtoupper($data['code']);
        $data['max_uses'] = (int) ($data['max_uses'] ?? 0);
        $data['project_ids'] = Project::whereIn('id', $data['project_ids'] ?? [])->pluck('id')->all() ?: null;

        return $data;
    }
}
