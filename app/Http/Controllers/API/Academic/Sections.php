<?php

namespace App\Http\Controllers\API\Academic;

use App\Http\Collections\Academic\Sections as SectionResource;
use App\Section;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Helpers\AppHelper;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class Sections extends Controller
{

    public $successStatus = 200;
    /**
     * Handle an authentication attempt.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Response
     */
    public function sections(Request $request)
    {
        $sections = Section::with('teacher')->with('class')->orderBy('name', 'asc')->get();

        return response()->json(['success' => true, 'data' => new SectionResource($sections)], $this->successStatus);

        die;
    }
}
