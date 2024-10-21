<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreateDriverOtpAPIRequest;
use App\Http\Requests\API\UpdateDriverOtpAPIRequest;
use App\Models\DriverOtp;
use App\Repositories\DriverOtpRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class DriverOtpAPIController
 */
class DriverOtpAPIController extends AppBaseController
{
    private DriverOtpRepository $driverOtpRepository;

    public function __construct(DriverOtpRepository $driverOtpRepo)
    {
        $this->driverOtpRepository = $driverOtpRepo;
    }

    /**
     * Display a listing of the DriverOtps.
     * GET|HEAD /driver-otps
     */
    public function index(Request $request): JsonResponse
    {
        $driverOtps = $this->driverOtpRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($driverOtps->toArray(), 'Driver Otps retrieved successfully');
    }

    /**
     * Store a newly created DriverOtp in storage.
     * POST /driver-otps
     */
    public function store(CreateDriverOtpAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $driverOtp = $this->driverOtpRepository->create($input);

        return $this->sendResponse($driverOtp->toArray(), 'Driver Otp saved successfully');
    }

    /**
     * Display the specified DriverOtp.
     * GET|HEAD /driver-otps/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var DriverOtp $driverOtp */
        $driverOtp = $this->driverOtpRepository->find($id);

        if (empty($driverOtp)) {
            return $this->sendError('Driver Otp not found');
        }

        return $this->sendResponse($driverOtp->toArray(), 'Driver Otp retrieved successfully');
    }

    /**
     * Update the specified DriverOtp in storage.
     * PUT/PATCH /driver-otps/{id}
     */
    public function update($id, UpdateDriverOtpAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var DriverOtp $driverOtp */
        $driverOtp = $this->driverOtpRepository->find($id);

        if (empty($driverOtp)) {
            return $this->sendError('Driver Otp not found');
        }

        $driverOtp = $this->driverOtpRepository->update($input, $id);

        return $this->sendResponse($driverOtp->toArray(), 'DriverOtp updated successfully');
    }

    /**
     * Remove the specified DriverOtp from storage.
     * DELETE /driver-otps/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var DriverOtp $driverOtp */
        $driverOtp = $this->driverOtpRepository->find($id);

        if (empty($driverOtp)) {
            return $this->sendError('Driver Otp not found');
        }

        $driverOtp->delete();

        return $this->sendSuccess('Driver Otp deleted successfully');
    }
}
