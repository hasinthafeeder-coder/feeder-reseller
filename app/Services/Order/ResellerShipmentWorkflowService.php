<?php

namespace App\Services\Order;

use Feeder\Core\Models\Order;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Courier\CourierBookingAdapterResolver;
use Feeder\Core\Services\Order\OrderCourierLookupService;
use Feeder\Core\Services\Order\ShipmentBookingService;
use Illuminate\Validation\ValidationException;

/**
 * Reseller Portal adapter for courier lookup and shipment booking.
 *
 * Company scoping is enforced before Core domain calls.
 * Controllers must still apply permission middleware.
 */
class ResellerShipmentWorkflowService
{
    public function __construct(
        private readonly ResellerOrderWorkflowService $orderWorkflowService,
        private readonly OrderCourierLookupService $courierLookupService,
        private readonly ShipmentBookingService $shipmentBookingService,
        private readonly CourierBookingAdapterResolver $adapterResolver,
    ) {}

    public function findOrFailForCompany(User $actor, string $orderUuid): Order
    {
        return $this->orderWorkflowService->findOrFailForCompany($actor, $orderUuid);
    }

    /**
     * @return list<array{id: int, uuid: string, code: string, name: string}>
     */
    public function couriers(User $actor, Order $order): array
    {
        return $this->courierLookupService->eligibleCouriers(
            $order,
            (int) $actor->company_id,
        );
    }

    /**
     * @return list<array{id: int, uuid: string, code: string, name: string}>
     */
    public function services(User $actor, Order $order, int $courierId): array
    {
        return $this->courierLookupService->servicesForCourier(
            $order,
            $courierId,
            (int) $actor->company_id,
        );
    }

    /**
     * @return list<array{id: int, uuid: string, name: string, district: string, external_id: string}>
     */
    public function districts(User $actor, Order $order, int $courierServiceId): array
    {
        return $this->courierLookupService->districtsForService(
            $order,
            $courierServiceId,
            (int) $actor->company_id,
        );
    }

    /**
     * @return list<array{id: int, uuid: string, city_name: string, district_name: string, external_city_code: string, courier_state_id: int|null}>
     */
    public function cities(
        User $actor,
        Order $order,
        int $courierServiceId,
        string $district,
        ?int $courierStateId = null,
    ): array {
        return $this->courierLookupService->citiesForServiceAndDistrict(
            $order,
            $courierServiceId,
            $district,
            (int) $actor->company_id,
            $courierStateId,
        );
    }

    /**
     * @return array{
     *     courier_fee_amount: float,
     *     customer_payable_amount: float,
     *     items_subtotal: float,
     *     discount_amount: float,
     *     total_weight: float
     * }
     */
    public function feePreview(User $actor, Order $order, int $courierId): array
    {
        return $this->courierLookupService->feePreview(
            $order,
            $courierId,
            (int) $actor->company_id,
        );
    }

    public function book(
        User $actor,
        Order $order,
        int $courierId,
        ?int $courierServiceId,
        int $courierCityId,
    ): Shipment {
        $courier = $this->courierLookupService->requireEligibleCourier($order, $courierId);

        if ($courierServiceId === null || $courierServiceId < 1) {
            $service = $this->courierLookupService->firstActiveServiceForCourier($courierId);

            if ($service === null) {
                throw ValidationException::withMessages([
                    'courier_service_id' => [
                        'No active courier service is available for the selected courier.',
                    ],
                ]);
            }

            $courierServiceId = (int) $service->id;
        }

        $adapter = $this->adapterResolver->resolve($courier);

        return $this->shipmentBookingService->book(
            $order,
            $courierId,
            $courierServiceId,
            $courierCityId,
            $adapter,
            (int) $actor->id,
            (int) $actor->company_id,
        );
    }

    /**
     * Normalized booking payload for reseller UI / JSON clients.
     *
     * @return array{
     *     courier: array{id: int, code: string, name: string},
     *     waybill: string,
     *     tracking_number: string,
     *     shipment_uuid: string,
     *     status: string
     * }
     */
    public function serializeBooking(Shipment $shipment): array
    {
        $shipment->loadMissing('courier');

        return [
            'courier' => [
                'id' => (int) $shipment->courier_id,
                'code' => (string) ($shipment->courier?->code ?? ''),
                'name' => (string) ($shipment->courier?->name ?? ''),
            ],
            'waybill' => (string) ($shipment->tracking_number ?? ''),
            'tracking_number' => (string) ($shipment->tracking_number ?? ''),
            'shipment_uuid' => (string) $shipment->uuid,
            'status' => $shipment->status instanceof \BackedEnum
                ? $shipment->status->value
                : (string) $shipment->status,
        ];
    }
}
