<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartamentoModel;
use App\Models\DistritoModel;
use App\Models\MunicipioModel;
use App\Models\PaisModel;
use App\Services\AgencyAccessService;
use CodeIgniter\HTTP\ResponseInterface;

class GeoController extends BaseController
{
    protected PaisModel $paisModel;
    protected DepartamentoModel $departamentoModel;
    protected MunicipioModel $municipioModel;
    protected DistritoModel $distritoModel;
    protected AgencyAccessService $accessService;

    public function __construct(
        ?PaisModel $paisModel = null,
        ?DepartamentoModel $departamentoModel = null,
        ?MunicipioModel $municipioModel = null,
        ?DistritoModel $distritoModel = null,
        ?AgencyAccessService $accessService = null
    ) {
        $this->paisModel         = $paisModel ?? new PaisModel();
        $this->departamentoModel = $departamentoModel ?? new DepartamentoModel();
        $this->municipioModel    = $municipioModel ?? new MunicipioModel();
        $this->distritoModel     = $distritoModel ?? new DistritoModel();
        $this->accessService     = $accessService ?? service('agencyAccess');
    }

    /**
     * GET api/geo/paises
     * Returns supervisor's country info.
     */
    public function paises(): ResponseInterface
    {
        $supervisorPaisId = $this->accessService->getSupervisorCountryId();
        if ($supervisorPaisId === null || $supervisorPaisId <= 0) {
            return $this->accessService->unauthorizedResponse($this->request);
        }

        $pais = $this->paisModel->findById($supervisorPaisId);
        if ($pais === null) {
            return $this->accessService->notFoundResponse($this->request, 'País no encontrado.');
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status' => 'ok',
            'paises' => [
                [
                    'id_pais'           => (int) ($pais['id_pais'] ?? $pais['Id_pais']),
                    'pais'              => (string) ($pais['pais'] ?? $pais['Pais']),
                    'requiere_distrito' => ((int) ($pais['requiere_distrito'] ?? 1)) === 1,
                ],
            ],
        ]);
    }

    /**
     * GET api/geo/departamentos
     * Returns departments for supervisor's country.
     */
    public function departamentos(): ResponseInterface
    {
        $supervisorPaisId = $this->accessService->getSupervisorCountryId();
        if ($supervisorPaisId === null || $supervisorPaisId <= 0) {
            return $this->accessService->unauthorizedResponse($this->request);
        }

        $paramPais = $this->request->getGet('id_pais');
        if ($paramPais !== null && trim((string) $paramPais) !== '') {
            $parsedPais = AgencyAccessService::parsePositiveInt($paramPais);
            if ($parsedPais === null || $parsedPais !== $supervisorPaisId) {
                return $this->accessService->forbiddenResponse(
                    $this->request,
                    'Acceso denegado: Restricción de jurisdicción territorial por país.'
                );
            }
        }

        $rows = $this->departamentoModel->findByPais($supervisorPaisId);
        $departamentos = [];
        foreach ($rows as $row) {
            $departamentos[] = [
                'id_departamento' => (int) ($row['id_departamento'] ?? $row['Id_departamento']),
                'departamento'    => (string) ($row['departamento'] ?? $row['Departamento']),
                'id_pais'         => (int) ($row['id_pais'] ?? $row['Id_pais']),
            ];
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status'        => 'ok',
            'departamentos' => $departamentos,
        ]);
    }

    /**
     * GET api/geo/municipios?id_departamento=X
     * Returns child municipalities for department.
     */
    public function municipios(): ResponseInterface
    {
        $rawDepto = $this->request->getGet('id_departamento');
        $idDepto  = AgencyAccessService::parsePositiveInt($rawDepto);
        if ($idDepto === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'error'   => 'bad_request',
                'message' => 'Parámetro id_departamento es requerido y debe ser un entero positivo.',
            ]);
        }

        $supervisorPaisId = $this->accessService->getSupervisorCountryId();
        if ($supervisorPaisId === null || $supervisorPaisId <= 0) {
            return $this->accessService->unauthorizedResponse($this->request);
        }

        $check = $this->accessService->checkDepartamentoOwnership($idDepto, $supervisorPaisId);
        if ($check['status'] === 'forbidden') {
            return $this->accessService->forbiddenResponse(
                $this->request,
                'Acceso denegado: el departamento pertenece a otro país.'
            );
        }
        if ($check['status'] === 'not_found') {
            return $this->accessService->notFoundResponse(
                $this->request,
                'Departamento no encontrado.'
            );
        }

        $rows = $this->municipioModel->findByDepartamento($idDepto);
        $municipios = [];
        foreach ($rows as $row) {
            $municipios[] = [
                'id_municipio'    => (int) ($row['id_municipio'] ?? $row['Id_municipio']),
                'municipio'       => (string) ($row['municipio'] ?? $row['Municipio']),
                'id_departamento' => (int) ($row['id_departamento'] ?? $row['Id_departamento']),
            ];
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status'     => 'ok',
            'municipios' => $municipios,
        ]);
    }

    /**
     * GET api/geo/distritos?id_municipio=X
     * Returns child districts for municipality.
     */
    public function distritos(): ResponseInterface
    {
        $rawMuni = $this->request->getGet('id_municipio');
        $idMuni  = AgencyAccessService::parsePositiveInt($rawMuni);
        if ($idMuni === null) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'error'   => 'bad_request',
                'message' => 'Parámetro id_municipio es requerido y debe ser un entero positivo.',
            ]);
        }

        $supervisorPaisId = $this->accessService->getSupervisorCountryId();
        if ($supervisorPaisId === null || $supervisorPaisId <= 0) {
            return $this->accessService->unauthorizedResponse($this->request);
        }

        $check = $this->accessService->checkMunicipioOwnership($idMuni, $supervisorPaisId);
        if ($check['status'] === 'forbidden') {
            return $this->accessService->forbiddenResponse(
                $this->request,
                'Acceso denegado: el municipio pertenece a otro país.'
            );
        }
        if ($check['status'] === 'not_found') {
            return $this->accessService->notFoundResponse(
                $this->request,
                'Municipio no encontrado.'
            );
        }

        $rows = $this->distritoModel->findByMunicipio($idMuni);
        $distritos = [];
        foreach ($rows as $row) {
            $distritos[] = [
                'id_distrito'  => (int) ($row['id_distrito'] ?? $row['Id_distrito']),
                'distrito'     => (string) ($row['distrito'] ?? $row['Distrito']),
                'id_municipio' => (int) ($row['id_municipio'] ?? $row['Id_municipio']),
            ];
        }

        return $this->response->setStatusCode(200)->setJSON([
            'status'    => 'ok',
            'distritos' => $distritos,
        ]);
    }
}
