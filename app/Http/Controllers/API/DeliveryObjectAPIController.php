<?php

namespace App\Http\Controllers\API;

use App\Models\DeliveryObject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class DeliveryObjectAPIController
 */
class DeliveryObjectAPIController extends AppBaseController
{
    /**
     * Display a listing of the DeliveryObjects.
     * GET|HEAD /delivery-objects
     */
    public function index(Request $request): JsonResponse
    {
        $deliveryObjects = DeliveryObject::query();

        if ($request->has('name')) {
            $deliveryObjects->where('name', 'like', '%'.$request->get('name').'%');
        }
        
        $deliveryObjects = $deliveryObjects->orderBy('name', 'asc')->get();

        return $this->sendResponse($deliveryObjects->toArray(), 'Delivery Objects retrieved successfully');
    }

    /**
     * Store a newly created DeliveryObject in storage.
     * POST /delivery-objects
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $deliveryObject = DeliveryObject::create($input);

        return $this->sendResponse($deliveryObject->toArray(), 'Delivery Object saved successfully');
    }

    /**
     * Display the specified DeliveryObject.
     * GET|HEAD /delivery-objects/{id}
     */
    public function show($id): JsonResponse
    {
        $deliveryObject = DeliveryObject::find($id);

        if (empty($deliveryObject)) {
            return $this->sendError('Delivery Object not found');
        }

        return $this->sendResponse($deliveryObject->toArray(), 'Delivery Object retrieved successfully');
    }

    /**
     * Update the specified DeliveryObject in storage.
     * PUT/PATCH /delivery-objects/{id}
     */
    public function update($id, Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $deliveryObject = DeliveryObject::find($id);

        if (empty($deliveryObject)) {
            return $this->sendError('Delivery Object not found');
        }

        $deliveryObject->update($input);

        return $this->sendResponse($deliveryObject->toArray(), 'DeliveryObject updated successfully');
    }

    /**
     * Remove the specified DeliveryObject from storage.
     * DELETE /delivery-objects/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        $deliveryObject = DeliveryObject::find($id);

        if (empty($deliveryObject)) {
            return $this->sendError('Delivery Object not found');
        }

        $deliveryObject->delete();

        return $this->sendSuccess('Delivery Object deleted successfully');
    }
}