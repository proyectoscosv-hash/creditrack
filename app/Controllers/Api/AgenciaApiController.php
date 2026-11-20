<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AgenciaModel;
use App\Services\AgencyAccessService;
use CodeIgniter\HTTP\ResponseInterface;

class AgenciaApiController extends BaseController
{
    protected AgenciaModel $agenciaModel;
    protected AgencyAccessService $agencyAccess;

    public function __construct(
        ?AgenciaModel $agenciaModel = null,
        ?AgencyAccessService $agencyAccess = null
    ) {
        $this->agenciaModel = $agenciaModel ?? new AgenciaModel();
        $this->agencyAccess = $agencyAccess ?? service('agencyAccess');
    }

    /**
     * GET api/agencias/geolocalizacion
     * Returns geolocation points for active agencies within supervisor's country.
     */
    public function geolocalizacion(): ResponseInterface
    {
        $auth = $this->agencyAccess->authorizeSupervisorRequest($this->request);
        if ($auth !== null) {
            return $auth;
        }

        $ctx = $this->agencyAccess->inspectSessionContext();
        $supervisorPaisId   = (int) $ctx['id_pais'];
        $supervisorPaisName = (string) $ctx['pais_nombre'];

        // Cross-country parameter checks for geographic hierarchy
        $rawDepto = $this->request->getGet('id_departamento');
        if ($rawDepto !== null && trim((string) $rawDepto) !== '') {
            $idDepto = AgencyAccessService::parsePositiveInt($rawDepto);
            if ($idDepto !== null) {
                $check = $this->agencyAccess->checkDepartamentoOwnership($idDepto, $supervisorPaisId);
                if ($check['status'] === 'forbidden') {
                    return $this->agencyAccess->forbiddenResponse(
                        $this->request,
                        'Acceso denegado: el departamento pertenece a otro país.'
                    );
                }
            }
        }

        $rawMuni = $this->request->getGet('id_municipio');
        if ($rawMuni !== null && trim((string) $rawMuni) !== '') {
            $idMuni = AgencyAccessService::parsePositiveInt($rawMuni);
            if ($idMuni !== null) {
                $check = $this->agencyAccess->checkMunicipioOwnership($idMuni, $supervisorPaisId);
                if ($check['status'] === 'forbidden') {
                    return $this->agencyAccess->forbiddenResponse(
                        $this->request,
                        'Acceso denegado: el municipio pertenece a otro país.'
                    );
                }
            }
        }

        $rawDist = $this->request->getGet('id_distrito');
        if ($rawDist !== null && trim((string) $rawDist) !== '') {
            $idDist = AgencyAccessService::parsePositiveInt($rawDist);
            if ($idDist !== null) {
                $check = $this->agencyAccess->checkDistritoOwnership($idDist, $supervisorPaisId);
                if ($check['status'] === 'forbidden') {
                    return $this->agencyAccess->forbiddenResponse(
                        $this->request,
                        'Acceso denegado: el distrito pertenece a otro país.'
                    );
                }
            }
        }

        $filters = [
            'id_marca'          => $this->request->getGet('id_marca'),
            'marca'             => $this->request->getGet('marca'),
            'id_departamento'   => $this->request->getGet('id_departamento'),
            'id_municipio'      => $this->request->getGet('id_municipio'),
            'id_distrito'       => $this->request->getGet('id_distrito'),
            'incluir_inactivas' => $this->request->getGet('incluir_inactivas'),
        ];

        $agencias = $this->agenciaModel->findForGeolocation($supervisorPaisId, $filters);

        return $this->response->setStatusCode(200)->setJSON([
            'status'   => 'ok',
            'pais'     => $supervisorPaisName,
            'total'    => count($agencias),
            'agencias' => $agencias,
        ]);
    }
}
