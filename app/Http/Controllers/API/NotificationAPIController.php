<?php

namespace App\Http\Controllers\API;

use App\Models\NotificationDeliveryStatus;
use Illuminate\Http\Request;

class NotificationAPIController extends Controller
{
    //

    public function confirmDelivery(Request $request)
    {
        $request->validate([
            'message_id' => 'required|string',
        ]);

        $status = NotificationDeliveryStatus::where('fcm_message_id', $request->message_id)
            ->first();

        if ($status) {
            $status->update([
                'status' => 'DELIVERED',
                'delivered_at' => now()
            ]);

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'not_found'], 404);
    }

}
