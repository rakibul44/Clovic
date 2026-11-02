<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\BrandPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BrandGalleryController extends Controller
{
    public function index()
    {
        return view('gallery', ['photos' => BrandPhoto::where('published', true)->orderBy('position')->orderBy('id')->paginate(24)]);
    }

    public function admin()
    {
        return view('admin.gallery', ['photos' => BrandPhoto::orderBy('position')->orderBy('id')->paginate(24)]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:publish,unpublish,delete',
            'all' => 'nullable|boolean',
            'ids' => 'required_unless:all,1|array|min:1|max:500',
            'ids.*' => 'required|integer|distinct|exists:brand_photos,id',
        ]);
        $count = DB::transaction(function () use ($request, $data) {
            $query = BrandPhoto::orderBy('id')->lockForUpdate();
            if (! $request->boolean('all')) {
                $query->whereIn('id', $data['ids']);
            }
            $photos = $query->get();
            if ($photos->isEmpty()) {
                throw ValidationException::withMessages(['ids' => 'Select at least one existing photo.']);
            }
            $ids = $photos->pluck('id')->all();
            if ($data['action'] === 'delete') {
                BrandPhoto::whereIn('id', $ids)->delete();
                $paths = $photos->pluck('path')->unique()->all();
                DB::afterCommit(function () use ($paths) {
                    foreach ($paths as $path) {
                        if (! BrandPhoto::where('path', $path)->exists()) {
                            Storage::disk('public')->delete($path);
                        }
                    }
                });
            } else {
                BrandPhoto::whereIn('id', $ids)->update(['published' => $data['action'] === 'publish']);
            }
            AdminAuditLog::create(['user_id' => auth()->id(), 'action' => 'gallery.bulk_'.$data['action'], 'subject' => 'brand_photos', 'changes' => ['ids' => $ids, 'count' => count($ids), 'all_pages' => $request->boolean('all')]]);

            return count($ids);
        });
        $verb = ['publish' => 'published', 'unpublish' => 'unpublished', 'delete' => 'deleted'][$data['action']];

        return back()->with('success', $count.' '.($count === 1 ? 'photo' : 'photos').' '.$verb.'.');
    }

    public function update(Request $request, int $id)
    {
        $photo = BrandPhoto::findOrFail($id);
        $data = $request->validate(['alt' => 'required|string|max:200', 'caption' => 'nullable|string|max:500', 'position' => 'required|integer|min:0|max:10000', 'published' => 'nullable|boolean', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=6000,max_height=6000']);
        $data['published'] = $request->boolean('published');
        unset($data['image']);
        $new = null;
        $old = $photo->path;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            [$data['width'],$data['height']] = getimagesize($file->getRealPath());
            $new = $file->store('brand-gallery', 'public');
            $data['path'] = $new;
        }
        try {
            DB::transaction(function () use ($photo, $data, $new, $old) {
                $photo->update($data);
                AdminAuditLog::create(['user_id' => auth()->id(), 'action' => 'gallery.updated', 'subject' => (string) $photo->id, 'changes' => ['published' => $data['published'], 'image_replaced' => (bool) $new]]);
                if ($new) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($old));
                }
            });
        } catch (\Throwable $e) {
            if ($new) {
                Storage::disk('public')->delete($new);
            }throw $e;
        }

        return back()->with('success', 'Gallery photo saved.');
    }

    public function delete(int $id)
    {
        DB::transaction(function () use ($id) {
            $photo = BrandPhoto::lockForUpdate()->findOrFail($id);
            $path = $photo->path;
            $photo->delete();
            AdminAuditLog::create(['user_id' => auth()->id(), 'action' => 'gallery.deleted', 'subject' => (string) $id]);
            DB::afterCommit(fn () => Storage::disk('public')->delete($path));
        });

        return back()->with('success', 'Photo and its gallery file removed.');
    }
}
