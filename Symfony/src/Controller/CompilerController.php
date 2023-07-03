<?php
/**
 * \file
 * \brief Functions used by the compiler backend.
 *
 * \author Dimitrios Christidis
 * \author Vasilis Georgitzikis
 *
 * \copyright (c) 2012-2013, The Codebender Development Team
 * \copyright Licensed under the Simplified BSD License
 */

namespace App\Controller;

use App\Service\CompilerHandler;
use App\Service\DeletionHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CompilerController extends AbstractController
{
    #[Route('/status', name: 'compiler_status_check', methods: ['GET'])]
    public function statusAction(): JsonResponse
    {
        return $this->json(
            ['success' => true, 'status' => 'OK']
        );
    }

    #[Route('/{authorizationKey}/test', name: 'compiler_test', methods: ['GET'])]
    public function testAction($authorizationKey): JsonResponse
    {
        $params = $this->generateParameters();

        if ($authorizationKey !== $params["authorizationKey"]) {
            return new JsonResponse([
                "success" => false,
                "step"    => 0,
                "message" => "Invalid authorization key."
            ]);
        }

        set_time_limit(0); // make the script execution time unlimited (otherwise the request may time out)

        // change the current Symfony root dir
        // chdir($this->get('kernel')->getRootDir()."/../");
        chdir($this->getParameter('kernel.project_dir'));

        //TODO: replace this with a less horrible way to handle phpunit
        // exec("phpunit -c app --stderr 2>&1", $output, $return_val);
        exec("bin/phpunit --do-not-cache-result --testdox --no-interaction --stderr 2>&1", $output, $return_val);

        return new JsonResponse([
            "success" => (bool) !$return_val,
            "message" => implode("\n", $output)
        ]);
    }

    #[Route('/{authorizationKey}/{version}', name: 'compiler_home', methods: ['GET', 'POST'])]
    public function indexAction($authorizationKey, $version, Request $request, CompilerHandler $compiler)
    {
        $params = $this->generateParameters();

        if ($authorizationKey !== $params['authorizationKey']) {
            return new JsonResponse([
                'success' => false,
                'step' => 0,
                'message' => 'Invalid authorization key.'
            ]);
        }

        // $requestObject = $this->getRequest();
        $content = $request->getContent();

        if ($version == 'v1') {
            // Custom headers used during library tests.
            // They don't affect the compiler's response and make our life easier.
            if ($request->headers->get('X-Set-Exec-Time') == 'true') {
                ini_set('max_execution_time', '30');
            }
            $mongoProjectId = $request->headers->get('X-Mongo-Id');

            //Get the compiler service
            // /** @var CompilerHandler $compiler */
            // $compiler = $this->getParameter('compiler_handler');

            $reply = $compiler->main($content, $params);
            if ($mongoProjectId != '') {
                $reply['mongo-id'] = $mongoProjectId;
            }

            return new JsonResponse($reply);
        }
        if ($version == 'v2') {
            /** @var CompilerV2Handler $compiler */
            $compiler = $this->getParameter('compiler_v2_handler');

            $reply = $compiler->main($request, $params);

            return new JsonResponse($reply);
        }

        return new JsonResponse([
            'success' => false,
            'step' => 0,
            'message' => 'Invalid API version.'
        ]);
    }

    #[Route('/{authorizationKey}/{version}/delete/all/', name: 'compiler_delete_all', methods: ['POST'])]
    public function deleteAllObjectsAction($authorizationKey, $version, DeletionHandler $deleter)
    {
        if ($this->getParameter('authorizationKey') != $authorizationKey) {
            return new JsonResponse([
                'success' => false,
                'step'    => 0,
                'message' => 'Invalid authorization key.'
            ]);
        }

        if (!in_array($version, ['v1', 'v2'])) {
            return new JsonResponse([
                'success' => false,
                'step'    => 0,
                'message' => 'Invalid API version.'
            ]);
        }

        //Get the compiler service
        /** @var DeletionHandler $deleter */
        // $deleter = $this->get('deletion_handler');

        $response = $deleter->deleteAllObjects();

        if ($response['success'] !== true) {
            return new JsonResponse([
                'success' => false,
                'step'    => 0,
                'message' => 'Failed to access object files directory.'
            ]);
        }

        return new JsonResponse(array_merge(
            [
                'success' => true,
                'message' => 'Object files deletion complete. Found ' . $response['fileCount'] . ' files.'
            ],
            $response['deletionStats'],
            ["Files not deleted" => $response['notDeletedFiles']]
        ));
    }

    #[Route('/{authorizationKey}/{version}/delete/{option}/{cachedObjectToDelete}', name: 'compiler_delete_specific', methods: ['POST'])]
    public function deleteSpecificObjectsAction($authorizationKey, $version, $option, $cachedObjectToDelete, DeletionHandler $deleter)
    {
        if ($this->getParameter('authorizationKey') != $authorizationKey) {
            return new JsonResponse([
                'success' => false, 'step' => 0,
                'message' => 'Invalid authorization key.'
            ]);
        }

        if (!in_array($version, ['v1', 'v2'])) {
            return new JsonResponse([
                'success' => false,
                'step' => 0,
                'message' => 'Invalid API version.'
            ]);
        }

        $response = $deleter->deleteSpecificObjects($option, $cachedObjectToDelete);

        if ($response['success'] !== true) {
            return new JsonResponse([
                'success' => false,
                'step' => 0,
                'message' => 'Failed to access object files directory.'
            ]);
        }

        if (!empty($response["notDeletedFiles"])) {
            $message = 'Failed to delete one or more of the specified core object files.';
            if ($option == 'library') {
                $message = 'Failed to delete one or more of the specified library object files.';
            }

            return new JsonResponse(
                array_merge(
                    ['success' => false, 'step' => 0, 'message' => $message],
                    $response
                )
            );
        }

        $message = 'Core object files deleted successfully.';
        if ($option == 'library') {
            $message = 'Library deleted successfully.';
        }

        return new JsonResponse(
            array_merge(
                ['success' => true, 'message' => $message],
                $response
            )
        );
    }

    /**
     * \brief Creates a list of the configuration parameters to be used in the compilation process.
     *
     * \return An array of the parameters.
     *
     * This function accesses the Symfony global configuration parameters,
     * and creates an array that our handlers (which don't have access to them)
     * can use them.
     */
    private function generateParameters()
    {
        $parameters = array(
                "binutils", "python", "clang", "logdir", "temp_dir",
                "archive_dir", "autocompletion_dir", "autocompleter",
                "cflags", "cppflags", "asflags", "arflags", "ldflags",
                "ldflags_tail", "clang_flags", "objcopy_flags", "size_flags",
                "output", "arduino_cores_dir", "external_core_files",
                "authorizationKey");

        $compiler_config = array();

        foreach ($parameters as $parameter) {
            $compiler_config[$parameter] = $this->getParameter($parameter);
        }

        return $compiler_config;
    }

}
