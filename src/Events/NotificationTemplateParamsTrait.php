<?php

namespace Crm\ScenariosModule\Events;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;

/**
 * @property SubscriptionsRepository $subscriptionsRepository
 * @property PaymentsRepository $paymentsRepository
 * @property RecurrentPaymentsRepository $recurrentPaymentsRepository
 * @property UsersRepository $usersRepository
 * @property AddressesRepository $addressesRepository
 * @property RecurrentPaymentsResolver $recurrentPaymentsResolver
 * @property ApplicationConfig $applicationConfig
 */
trait NotificationTemplateParamsTrait
{
    public function getNotificationTemplateParams(object $scenarioJobParams): array
    {
        $subscription = $this->getSubscription($scenarioJobParams);
        $payment = $this->getPayment($scenarioJobParams, $subscription);
        $recurrentPayment = $this->getRecurrentPayment($scenarioJobParams, $payment);

        return [
            ...$this->getUserParams($scenarioJobParams),
            ...$this->getPasswordParams($scenarioJobParams),
            ...$this->getRenewalPaymentParams($scenarioJobParams),
            ...$this->getRecurrentPaymentParams($recurrentPayment),
            ...$this->getRecurrentParentPaymentParams($recurrentPayment),
            ...$this->getAddressParams($scenarioJobParams),
            ...$this->getSubscriptionParams($subscription),
            ...$this->getSubscriptionTypeParams($subscription, $payment, $recurrentPayment),
            ...$this->getPaymentParams($payment),
            ...$this->getSupplierParams(),
        ];
    }

    private function getSubscription(object $scenarioJobParams): ?ActiveRow
    {
        $subscription = isset($scenarioJobParams->subscription_id) ? $this->subscriptionsRepository->find($scenarioJobParams->subscription_id) : null;
        $payment = isset($scenarioJobParams->payment_id) ? $this->paymentsRepository->find($scenarioJobParams->payment_id) : null;

        if (!$subscription && $payment && isset($payment->subscription)) {
            return $payment->subscription;
        }

        return $subscription;
    }

    private function getPayment(object $scenarioJobParams, ?ActiveRow $subscription): ?ActiveRow
    {
        $payment = isset($scenarioJobParams->payment_id) ? $this->paymentsRepository->find($scenarioJobParams->payment_id) : null;
        if (!$payment && $subscription) {
            return $this->paymentsRepository->subscriptionPayment($subscription);
        }

        return $payment;
    }

    private function getRecurrentPayment(object $scenarioJobParams, ?ActiveRow $payment): ?ActiveRow
    {
        if (isset($scenarioJobParams->recurrent_payment_id)) {
            return $this->recurrentPaymentsRepository->find($scenarioJobParams->recurrent_payment_id);
        }

        if ($payment !== null) {
            return $this->recurrentPaymentsRepository->recurrent($payment);
        }

        return null;
    }

    private function getUserParams(object $scenarioJobParams): array
    {
        $user = $scenarioJobParams->user_id ? $this->usersRepository->find($scenarioJobParams->user_id) : null;
        if ($user === null) {
            return [];
        }

        return [
            'user' => $user->toArray(),
            'email' => $user->email,
        ];
    }

    private function getPasswordParams(object $scenarioJobParams): array
    {
        $password = $scenarioJobParams->password ?? null;
        if ($password === null) {
            return [];
        }

        return [
            'password' => $password,
        ];
    }

    private function getRenewalPaymentParams(object $scenarioJobParams): array
    {
        // this is here to allow `Crm\PaymentsModule\Events\CreateNewPaymentEventHandler` add `renewal_payment` job parameter remp/novydenik#1147
        $renewalPayment = isset($scenarioJobParams->renewal_payment_id) ? $this->paymentsRepository->find($scenarioJobParams->renewal_payment_id) : null;
        if ($renewalPayment === null) {
            return [];
        }

        return [
            'renewal_payment' => $renewalPayment->toArray(),
        ];
    }

    private function getAddressParams(object $scenarioJobParams): array
    {
        $address = isset($scenarioJobParams->address_id) ? $this->addressesRepository->find($scenarioJobParams->address_id) : null;
        if ($address === null) {
            return [];
        }

        return [
            'address' => $address->toArray(),
        ];
    }

    private function getSubscriptionParams(?ActiveRow $subscription): array
    {
        if ($subscription === null) {
            return [];
        }

        return [
            'subscription' => $subscription->toArray(),
        ];
    }

    private function getPaymentParams(?ActiveRow $payment): array
    {
        if ($payment === null) {
            return [];
        }

        return [
            'payment' => $payment->toArray(),
        ];
    }

    private function getSubscriptionTypeParams(?ActiveRow $subscription, ?ActiveRow $payment, ?ActiveRow $recurrentPayment): array
    {
        $subscriptionType = null;

        if ($subscription !== null) {
            $subscriptionType = $subscription->subscription_type;
        } elseif ($payment !== null) {
            $subscriptionType = $payment->subscription_type;
        } elseif ($recurrentPayment !== null) {
            $subscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        }

        if ($subscriptionType === null) {
            return [];
        }

        return [
            'subscription_type' => $subscriptionType->toArray(),
        ];
    }

    private function getRecurrentPaymentParams(?ActiveRow $recurrentPayment): array
    {
        if ($recurrentPayment === null) {
            return [];
        }

        return [
            'recurrent_payment' => $recurrentPayment->toArray(),
        ];
    }

    private function getRecurrentParentPaymentParams(?ActiveRow $recurrentPayment): array
    {
        $recurrentParentPayment = $recurrentPayment?->parent_payment;
        if ($recurrentParentPayment === null) {
            return [];
        }

        return [
            'recurrent_parent_payment' => $recurrentParentPayment->toArray(),
        ];
    }

    private function getSupplierParams(): array
    {
        return [
            'supplier' => [
                'bank_account' => [
                    'number' => $this->applicationConfig->get('supplier_bank_account_number'),
                    'iban' => $this->applicationConfig->get('supplier_iban'),
                    'swift' => $this->applicationConfig->get('supplier_swift'),
                ],
            ],
        ];
    }
}
