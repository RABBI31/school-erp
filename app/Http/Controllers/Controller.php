<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    private const MYSQL_ROW_IS_REFERENCED = 1451;

    /**
     * Delete, or tell the user the record is still used elsewhere (a
     * restrictOnDelete foreign key refused it).
     */
    protected function destroyOrRefuse(Model $model): RedirectResponse
    {
        try {
            $model->delete();
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== self::MYSQL_ROW_IS_REFERENCED) {
                throw $e;
            }

            return back()->with('error', __('messages.in_use'));
        }

        return back()->with('success', __('messages.deleted'));
    }
}
