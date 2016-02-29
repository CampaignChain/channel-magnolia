<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\MagnoliaBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\CoreBundle\Util\ParserUtil;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Url;

class MagnoliaController extends Controller
{
    public function newAction(Request $request)
    {
        $locationType = $this->get('campaignchain.core.form.type.location');
        $locationType->setBundleName('campaignchain/location-website');
        $locationType->setModuleIdentifier('campaignchain-website');

        $form = $this->createFormBuilder()
            ->add('URL', 'url', array(
                'label' => 'Website URL',
                'constraints' => array(
                    new Url(array(
                        'checkDNS'  => true,
                    )),
                )))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isValid()) {
            $locationURL = $form->getData()['URL'];
            $locationName = ParserUtil::getHTMLTitle($locationURL, $locationURL);
            $locationService = $this->get('campaignchain.core.location');
            $locationModule = $locationService->getLocationModule('campaignchain/location-website', 'campaignchain-website');

            $location = new Location();
            $location->setLocationModule($locationModule);
            $location->setName($locationName);
            $location->setUrl($locationURL);

            // Get the Website's favicon as Channel image if possible.
            $favicon = ParserUtil::getFavicon($locationURL);
            if($favicon){
                $locationImage = $favicon;
            } else {
//                $locationImage = $this->container->get('templating.helper.assets')
//                    ->getUrl(
//                        'bundles/campaignchainlocationwebsite/images/icons/256x256/website.png',
//                        null
//                    );
                $locationImage = null;
            }
            $location->setImage($locationImage);

            $wizard = $this->get('campaignchain.core.channel.wizard');
            $wizard->setName($location->getName());

            $repository = $this->getDoctrine()
                ->getRepository('CampaignChainCoreBundle:Location');
            if(!$repository->findBy(array('url' => $location->getUrl())))
            {
                $wizard->addLocation($location->getUrl(), $location);
                try {
                    $channel = $wizard->persist();
                    $wizard->end();
                    $this->get('session')->getFlashBag()->add(
                        'success',
                        "The Website '" . $location->getUrl() . "' has been connected."
                    );
                    return $this->redirect($this->generateUrl(
                        'campaignchain_core_channel'));


                } catch(\Exception $e) {
                    $this->addFlash('warning',
                        "An error occured during the creation of the website location");
                    $this->get('logger')->addError($e->getMessage());
                }
            }
            else{
                $this->addFlash('warning',
                    "The website  '" . $location->getUrl() . "' already exists.");
            }
            }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Connect Website',
                'form' => $form->createView(),
            ));
    }
}
