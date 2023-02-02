<?php

namespace Crm\ScenariosModule\Events;

trait NotificationTemplateParamsTrait
{
    public function getNotificationTemplateParams(object $scenarioJobParams): array
    {
        $user = isset($scenarioJobParams->user_id) ? $this->usersRepository->find($scenarioJobParams->user_id) : null;
        $password = $scenarioJobParams->password ?? null;
        $subscription = isset($scenarioJobParams->subscription_id) ? $this->subscriptionsRepository->find($scenarioJobParams->subscription_id) : null;
        $payment = isset($scenarioJobParams->payment_id) ? $this->paymentsRepository->find($scenarioJobParams->payment_id) : null;
        $address = isset($scenarioJobParams->address_id) ? $this->addressesRepository->find($scenarioJobParams->address_id) : null;

        if ($payment && !$subscription && isset($payment->subscription)) {
            $subscription = $payment->subscription;
        }

        $recurrentPayment = null;
        if (isset($scenarioJobParams->recurrent_payment_id)) {
            $recurrentPayment = $this->recurrentPaymentsRepository->find($scenarioJobParams->recurrent_payment_id);
        } elseif ($payment !== null) {
            $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        }

        $subscriptionType = null;
        if ($subscription !== null) {
            $subscriptionType = $subscription->subscription_type;
        } elseif ($payment !== null) {
            $subscriptionType = $payment->subscription_type;
        } elseif ($recurrentPayment !== null) {
            $subscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        }

        $templateParams = [];
        if ($user) {
            $templateParams['user'] = $user->toArray();
            $templateParams['email'] = $user->email;
        }

        if ($password) {
            $templateParams['password'] = $password;
        }
        if ($subscription) {
            $templateParams['subscription'] = $subscription->toArray();
        }
        if ($subscriptionType) {
            $templateParams['subscription_type'] = $subscriptionType->toArray();
        }
        if ($payment) {
            $templateParams['payment'] = $payment->toArray();
        }
        if ($recurrentPayment) {
            $templateParams['recurrent_payment'] = $recurrentPayment->toArray();
        }
        if ($address) {
            $templateParams['address'] = $address->toArray();
        }

        return $templateParams;
    }
}
