<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\User;
use App\Services\AdminAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AdminRecordController extends Controller
{
    private function audit(string $action, string $subject, array $changes = []): void
    {
        AdminAuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'changes' => $changes]);
    }

    public function deleteContent(string $resource, int $id)
    {
        if ($resource === 'categories') {
            return app(AdminOperationsController::class)->deleteCategory($id);
        }
        $classes = ['pages' => Page::class, 'blog' => BlogPost::class, 'coupons' => Coupon::class];
        abort_unless(isset($classes[$resource]), 404);
        DB::transaction(function () use ($resource, $id, $classes) {
            $record = $classes[$resource]::lockForUpdate()->findOrFail($id);
            if ($resource === 'coupons' && ($record->used > 0 || DB::table('coupon_redemptions')->where('coupon_id', $id)->exists())) {
                throw ValidationException::withMessages(['delete' => 'This coupon has redemption history. Disable it instead of deleting it.']);
            }
            $image = $resource === 'blog' ? $record->image : null;
            $record->delete();
            $this->audit('content.deleted', $resource.':'.$id);
            if ($image) {
                DB::afterCommit(function () use ($image) {
                    if (! BlogPost::where('image', $image)->exists() && ! Category::where('image', $image)->exists()) {
                        Storage::disk('public')->delete($image);
                    }
                });
            }
        });

        return redirect('/admin/content/'.$resource)->with('success', 'Entry deleted.');
    }

    public function deleteReview(int $id)
    {
        DB::transaction(function () use ($id) {
            $review = DB::table('reviews')->where('id', $id)->lockForUpdate()->first();
            abort_unless($review, 404);
            DB::table('reviews')->where('id', $id)->delete();
            $this->audit('review.deleted', (string) $id);
        });

        return back()->with('success', 'Review deleted. The purchase record is preserved.');
    }

    public function customerStatus(Request $request, int $id)
    {
        $data = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($id, $data) {
            $user = User::where('role', 'customer')->lockForUpdate()->findOrFail($id);
            $user->forceFill($data)->save();
            $this->audit('customer.status', (string) $id, ['active' => $user->active]);
        });

        return back()->with('success', 'Customer access updated. Order history is preserved.');
    }

    public function staffStatus(Request $request, int $id)
    {
        abort_unless(AdminAccess::can($request->user(), 'staff'), 403);
        $data = $request->validate(['active' => 'required|boolean']);
        DB::transaction(function () use ($id, $data) {
            $user = User::where('role', '!=', 'customer')->lockForUpdate()->findOrFail($id);
            abort_if($user->role === 'owner' || $user->id === auth()->id(), 403);
            $user->forceFill($data)->save();
            $this->audit('staff.status', (string) $id, ['active' => $user->active]);
        });

        return back()->with('success', 'Staff access updated. Activity history is preserved.');
    }

    public function removeSection(int $id)
    {
        DB::transaction(function () use ($id) {
            $section = DB::table('homepage_sections')->where('id', $id)->lockForUpdate()->first();
            abort_unless($section, 404);
            DB::table('homepage_sections')->where('id', $id)->update(['published' => false, 'updated_at' => now()]);
            $this->audit('homepage.removed', (string) $id);
        });

        return back()->with('success', 'Section removed from the homepage. Enable it again to restore it.');
    }
}
