<?php



namespace InovCom\Kernel\Contracts;



use Illuminate\Support\Collection;



/**

 * Optional pharmacy bridge: Sales (and others) may call this when bound.

 * Commerce apps without the Prescriptions package never bind it — sales stays unchanged.

 */

interface PrescriptionsApi

{

    /**

     * True when the prescriptions tables exist for the current tenant.

     */

    public function isAvailable(): bool;



    /**

     * Active prescriptions still usable at POS (remaining qty, not past valid_until).

     *

     * @return Collection<int, object>

     */

    public function listActiveForSale(?int $clientId = null): Collection;



    /**

     * Search continuable prescriptions (number, client name/phone).

     *

     * @return list<array{id:int,number:string,client_name:?string,status_label:string,valid_until:?string,lines_summary:string,remaining_total:float}>

     */

    public function searchForSale(string $query, ?int $clientId = null, int $limit = 15): array;



    /**

     * Quick-create an active prescription and return a POS snapshot.

     *

     * @param  array{client_id:int,prescriber_name?:?string,prescriber_contact?:?string,valid_until?:?string,notes?:?string,lines:list<array{item_id:int,quantity:float|int|string,instructions?:?string}>}  $data

     * @return array{id:int,number:string,status_label:string,lines_summary:string}

     */

    public function createQuickForSale(array $data): array;



    /**

     * Snapshot for UI chip on the sale form.

     *

     * @return array{id:int,number:string,status_label:string,client_name:?string,valid_until:?string,lines_summary:string,remaining_total:float}|null

     */

    public function snapshotForSale(int $prescriptionId): ?array;



    /**

     * Close remaining quantity (patient will not come back). Keeps dispensed history.

     */

    public function closeRemaining(int $prescriptionId, ?string $reason = null): void;



    /**

     * Mark overdue active prescriptions as expired (valid_until < today).

     */

    public function expireOverdue(): int;



    /**

     * After a sale is saved: increment dispensed qty and update Rx status.

     *

     * @param  array<int, array{item_id: int, quantity: float, is_set?: bool}>  $cartLines

     * @return list<array{item_id: int, quantity: float, prescription_line_id: int}>

     */

    public function applyDispensationFromSale(int $prescriptionId, array $cartLines): array;



    /**

     * @param  array<int, array{item_id?: int|null, quantity?: float|int|string, conversion_factor?: float|int|string, item_name?: string|null, metadata?: array|null}>  $saleLines

     * @return array{number: string, status: string, status_label: string, lines: list<array{item_name: string, prescribed: float, this_sale: float, dispensed: float, remaining: float}>}|null

     */

    public function saleDispensationSummary(int $prescriptionId, array $saleLines): ?array;

}


