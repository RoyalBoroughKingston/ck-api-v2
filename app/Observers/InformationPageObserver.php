<?php

namespace App\Observers;

use App\Models\InformationPage;

class InformationPageObserver
{
    /**
     * Handle the information page "deleting" event.
     *
     * @param \App\Models\InformationPage $informationPage
     */
    public function deleting(InformationPage $informationPage)
    {
        if ($informationPage->image_file_id) {
            $image = $informationPage->image;
            $informationPage->update([
                'image_file_id' => null,
            ]);
            $image->delete();
        }
    }
}
