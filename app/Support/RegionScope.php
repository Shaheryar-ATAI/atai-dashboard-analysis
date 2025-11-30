<?php
namespace App\Support;

use Illuminate\Http\Request;

class RegionScope {
    public static function apply(Request $request) {
        $u = $request->user();

        if ($u && $u->hasAnyRole(['sales_eastern','sales_central','sales_western'])) {
            return $u->region; // e.g. "Eastern"
        }
        return null; // GM/Admin -> all regions
    }
}
