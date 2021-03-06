<?php

namespace Pilot\OgonePaymentBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Pilot\OgonePaymentBundle\Entity\OgoneClient;
use Pilot\OgonePaymentBundle\Entity\OgoneAlias;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends Controller
{
    public function indexAction()
    {
        $client = $this->getRepository('PilotOgonePaymentBundle:OgoneClient')->findOneBy(array(
            'email' => 'test@test.com',
        ));

        if (!$client) {
            $client = new OgoneClient();
            $client->setEmail('test@test.com');

            $this->getManager()->persist($client);
            $this->getManager()->flush();
        }

        $transaction = $this->get('ogone.transaction_builder')
            ->order()
                ->setClient($client)
                ->setAmount(99)
            ->end()
            ->configure()
                ->setBgColor('#ffffff')
                ->setAcceptUrl($this->generateUrl('ogone_payment_feedback', array(), UrlGeneratorInterface::ABSOLUTE_URL))
                ->setDeclineUrl($this->generateUrl('ogone_payment_feedback', array(), UrlGeneratorInterface::ABSOLUTE_URL))
                ->setExceptionUrl($this->generateUrl('ogone_payment_feedback', array(), UrlGeneratorInterface::ABSOLUTE_URL))
                ->setCancelUrl($this->generateUrl('ogone_payment_feedback', array(), UrlGeneratorInterface::ABSOLUTE_URL))
                ->setBackUrl($this->generateUrl('ogone_payment_feedback', array(), UrlGeneratorInterface::ABSOLUTE_URL))
            ->end()
        ;

        $transaction->save();

        if ($this->container->getParameter('ogone.use_aliases')) {
            $alias = $this->getRepository('PilotOgonePaymentBundle:OgoneAlias')->findOneBy(array(
                'client' => $client,
                'operation' => OgoneAlias::OPERATION_BYMERCHANT,
                'name' => 'ABONNEMENT',
            ));

            if (!$alias) {
                $alias = new OgoneAlias();
                $alias
                    ->setClient($client)
                    ->setOperation(OgoneAlias::OPERATION_BYMERCHANT)
                    ->setStatus(OgoneAlias::STATUS_ACTIVE)
                    ->setName('ABONNEMENT')
                ;

                $this->getManager()->persist($alias);
                $this->getManager()->flush();
            }

            $transaction->useAlias($alias);
        }

        $form = $transaction->getForm();

        return $this->render(
            'PilotOgonePaymentBundle:Payment:index.html.twig',
            array(
                'form' => $form->createView(),
            )
        );
    }

    public function feedbackAction()
    {
        if (!$this->get('ogone.feedbacker')->isValidCall()) {
            throw $this->createNotFoundException();
        }

        if ($this->get('ogone.feedbacker')->hasOrder()) {
            $this->get('ogone.feedbacker')->updateOrder();
        }

        if ($this->get('ogone.feedbacker')->hasAlias()) {
            $this->get('ogone.feedbacker')->updateAlias();
        }

        return $this->render(
            'PilotOgonePaymentBundle:Payment:feedback.html.twig'
        );
    }

    public function renderTemplateAction($twigPath)
    {
        $context = array();

        if ($this->get('request')->get('context')) {
            $context = json_decode(base64_decode($this->get('request')->get('context')), true);
        }

        return $this->render(
            $twigPath,
            $context
        );
    }

    protected function getRepository($name)
    {
        return $this->getManager()->getRepository($name);
    }

    protected function getManager()
    {
        return $this->getDoctrine()->getManager();
    }
}
