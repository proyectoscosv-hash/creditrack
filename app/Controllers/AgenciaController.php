<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AgenciaModel;
use App\Models\AuditoriaAgenciaModel;
use App\Models\DepartamentoModel;
use App\Models\DistritoModel;
use App\Models\MarcaModel;
use App\Models\MunicipioModel;
use App\Models\PaisModel;
use App\Services\AgencyAccessService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

class AgenciaController extends BaseController
{
    protected AgenciaModel $agenciaModel;
    protected AuditoriaAgenciaModel $auditoriaModel;
    protected PaisModel $paisModel;
    protected MarcaModel $marcaModel;
    protected DepartamentoModel $departamentoModel;
    protected MunicipioModel $municipioModel;
    protected DistritoModel $distritoModel;
    protected AgencyAccessService $agencyAccess;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->agenciaModel      = model(AgenciaModel::class);
        $this->auditoriaModel    = model(AuditoriaAgenciaModel::class);
        $this->paisModel         = model(PaisModel::class);
        $this->marcaModel        = model(MarcaModel::class);
        $this->departamentoModel = model(DepartamentoModel::class);
        $this->municipioModel    = model(MunicipioModel::class);
        $this->distritoModel     = model(DistritoModel::class);
        $this->agencyAccess      = service('agencyAccess');
    }

    /**
     * Helper to determine if the request expects JSON
     */
    protected function isJsonRequest(): bool
    {
        return $this->agencyAccess->isApiRequest($this->request);
    }

    /**
     * GET agencias
     */
    public function index()
    {
        $idPais = (int) session('id_pais');
        if ($idPais <= 0) {
            return redirect()->to(site_url('login'));
        }

        $filters = [
            'estado'          => (string) ($this->request->getGet('estado') ?? 'activas'),
            'id_departamento' => (int) ($this->request->getGet('id_departamento') ?? 0),
            'id_marca'        => (int) ($this->request->getGet('id_marca') ?? 0),
            'q'               => trim((string) ($this->request->getGet('q') ?? '')),
        ];

        $agencias      = $this->agenciaModel->findAllByPaisWithDetails($idPais, $filters);
        $departamentos = $this->departamentoModel->findByPais($idPais);
        $marcas        = $this->marcaModel->findAllOrdered();
        $paisNombre    = $this->agencyAccess->getSupervisorCountryName();

        $data = [
            'agencias'      => $agencias,
            'departamentos' => $departamentos,
            'marcas'        => $marcas,
            'filtros'       => $filters,
            'paisNombre'    => $paisNombre,
            'total'         => count($agencias),
        ];

        return view('agencias/index', $data);
    }

    /**
     * GET agencias/crear
     */
    public function crear()
    {
        $idPais           = (int) session('id_pais');
        $pais             = $this->paisModel->findById($idPais);
        $departamentos    = $this->departamentoModel->findByPais($idPais);
        $marcas           = $this->marcaModel->findAllOrdered();
        $requiereDistrito = ((int) ($pais['requiere_distrito'] ?? 1)) === 1;

        $data = [
            'mode'              => 'crear',
            'actionUrl'         => site_url('agencias/guardar'),
            'pais'              => $pais,
            'departamentos'     => $departamentos,
            'marcas'            => $marcas,
            'requiere_distrito' => $requiereDistrito,
            'agency'            => [
                'nombre_agencia'  => '',
                'direccion'       => '',
                'id_pais'         => $idPais,
                'id_departamento' => '',
                'id_municipio'    => '',
                'id_distrito'     => '',
                'latitud'         => '',
                'longitud'        => '',
                'marca_ids'       => [],
            ],
        ];

        return view('agencias/form', $data);
    }

    /**
     * POST agencias/guardar (alias POST agencias)
     */
    public function guardar()
    {
        $idPais = (int) session('id_pais');

        // Country spoofing/cross-country check
        $rawPais = $this->request->getPost('id_pais');
        if ($rawPais !== null && (int) $rawPais !== $idPais) {
            return $this->agencyAccess->forbiddenResponse(
                $this->request,
                'Acceso denegado: el país no corresponde al supervisor.'
            );
        }

        $postData = $this->request->getPost();
        unset($postData['codigo_empleado'], $postData['Codigo_Empleado'], $postData['deshabilitado_por']);
        $postData['id_pais'] = $idPais;

        // Guatemala optional district normalization
        $pais = $this->paisModel->findById($idPais);
        $requiereDistrito = ((int) ($pais['requiere_distrito'] ?? 1)) === 1;
        if (! $requiereDistrito) {
            $distVal = $postData['id_distrito'] ?? null;
            if ($distVal === null || (is_string($distVal) && trim($distVal) === '')) {
                $postData['id_distrito'] = null;
            }
        }

        $rawMarcas = $postData['marcas'] ?? [];
        if (! is_array($rawMarcas)) {
            $rawMarcas = $rawMarcas !== '' && $rawMarcas !== null ? [$rawMarcas] : [];
        }

        $codigoEmpleado = (string) session('codigo_empleado');

        try {
            $idAgencia = $this->agenciaModel->createWithBrandsAndAudit($postData, $rawMarcas, $codigoEmpleado);

            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(201)->setJSON([
                    'status'    => 'ok',
                    'message'   => 'Agencia creada exitosamente.',
                    'idagencia' => $idAgencia,
                ]);
            }

            return redirect()->to(site_url('agencias'))
                ->with('success', 'Agencia creada exitosamente.');
        } catch (InvalidArgumentException $e) {
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (DomainException $e) {
            return $this->agencyAccess->forbiddenResponse($this->request, $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', '[AgenciaController::guardar] ' . $e->getMessage());
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => 'error',
                    'message' => 'Error interno al registrar la agencia.',
                ]);
            }

            return redirect()->back()->withInput()->with('error', 'Error al procesar el registro de la agencia.');
        }
    }

    /**
     * GET agencias/ver/(:num)
     */
    public function ver($id = null)
    {
        $auth = $this->agencyAccess->authorizeAgencyAccess((int) $id);
        if ($auth['status'] === 'not_found') {
            return $this->agencyAccess->notFoundResponse($this->request, $auth['message']);
        }
        if ($auth['status'] === 'forbidden') {
            return $this->agencyAccess->forbiddenResponse($this->request, $auth['message']);
        }

        $agency     = $auth['agency'];
        $auditTrail = $this->auditoriaModel->findByAgencia((int) $id);

        $data = [
            'agency'     => $agency,
            'auditTrail' => $auditTrail,
        ];

        return view('agencias/ver', $data);
    }

    /**
     * GET agencias/editar/(:num)
     */
    public function editar($id = null)
    {
        $auth = $this->agencyAccess->authorizeAgencyAccess((int) $id);
        if ($auth['status'] === 'not_found') {
            return $this->agencyAccess->notFoundResponse($this->request, $auth['message']);
        }
        if ($auth['status'] === 'forbidden') {
            return $this->agencyAccess->forbiddenResponse($this->request, $auth['message']);
        }

        $agency           = $auth['agency'];
        $idPais           = (int) $agency['id_pais'];
        $pais             = $this->paisModel->findById($idPais);
        $departamentos    = $this->departamentoModel->findByPais($idPais);
        $municipios       = (int) ($agency['id_departamento'] ?? 0) > 0 ? $this->municipioModel->findByDepartamento((int) $agency['id_departamento']) : [];
        $distritos        = (int) ($agency['id_municipio'] ?? 0) > 0 ? $this->distritoModel->findByMunicipio((int) $agency['id_municipio']) : [];
        $marcas           = $this->marcaModel->findAllOrdered();
        $requiereDistrito = ((int) ($pais['requiere_distrito'] ?? 1)) === 1;

        $data = [
            'mode'              => 'editar',
            'actionUrl'         => site_url('agencias/actualizar/' . $id),
            'agency'            => $agency,
            'pais'              => $pais,
            'departamentos'     => $departamentos,
            'municipios'        => $municipios,
            'distritos'         => $distritos,
            'marcas'            => $marcas,
            'requiere_distrito' => $requiereDistrito,
        ];

        return view('agencias/form', $data);
    }

    /**
     * POST agencias/actualizar/(:num)
     */
    public function actualizar($id = null)
    {
        $auth = $this->agencyAccess->authorizeAgencyAccess((int) $id);
        if ($auth['status'] === 'not_found') {
            return $this->agencyAccess->notFoundResponse($this->request, $auth['message']);
        }
        if ($auth['status'] === 'forbidden') {
            return $this->agencyAccess->forbiddenResponse($this->request, $auth['message']);
        }

        $idPais = (int) session('id_pais');

        // Country immutability & spoof check
        $rawPais = $this->request->getPost('id_pais');
        if ($rawPais !== null && (int) $rawPais !== $idPais) {
            return $this->agencyAccess->forbiddenResponse(
                $this->request,
                'Acceso denegado: el país de la agencia no puede ser modificado.'
            );
        }

        $postData = $this->request->getPost();
        unset($postData['codigo_empleado'], $postData['Codigo_Empleado'], $postData['deshabilitado_por']);
        $postData['id_pais'] = $idPais;

        // Guatemala optional district normalization
        $pais = $this->paisModel->findById($idPais);
        $requiereDistrito = ((int) ($pais['requiere_distrito'] ?? 1)) === 1;
        if (! $requiereDistrito) {
            $distVal = $postData['id_distrito'] ?? null;
            if ($distVal === null || (is_string($distVal) && trim($distVal) === '')) {
                $postData['id_distrito'] = null;
            }
        }

        $rawMarcas = $postData['marcas'] ?? [];
        if (! is_array($rawMarcas)) {
            $rawMarcas = $rawMarcas !== '' && $rawMarcas !== null ? [$rawMarcas] : [];
        }

        $codigoEmpleado = (string) session('codigo_empleado');

        try {
            $this->agenciaModel->updateWithBrandsAndAudit((int) $id, $idPais, $postData, $rawMarcas, $codigoEmpleado);

            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(200)->setJSON([
                    'status'  => 'ok',
                    'message' => 'Agencia actualizada exitosamente.',
                ]);
            }

            return redirect()->to(site_url('agencias/ver/' . $id))
                ->with('success', 'Agencia actualizada exitosamente.');
        } catch (InvalidArgumentException $e) {
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (DomainException $e) {
            return $this->agencyAccess->forbiddenResponse($this->request, $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', '[AgenciaController::actualizar] ' . $e->getMessage());
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'status'  => 'error',
                    'message' => 'Error interno al actualizar la agencia.',
                ]);
            }

            return redirect()->back()->withInput()->with('error', 'Error al procesar la actualización.');
        }
    }

    /**
     * POST agencias/deshabilitar/(:num)
     */
    public function deshabilitar($id = null)
    {
        $auth = $this->agencyAccess->authorizeAgencyAccess((int) $id);
        if ($auth['status'] === 'not_found') {
            return $this->agencyAccess->notFoundResponse($this->request, $auth['message']);
        }
        if ($auth['status'] === 'forbidden') {
            return $this->agencyAccess->forbiddenResponse($this->request, $auth['message']);
        }

        $motivo = trim((string) $this->request->getPost('motivo_deshabilitacion'));
        if ($motivo === '') {
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'message' => 'El motivo de deshabilitación es obligatorio.',
                ]);
            }

            return redirect()->back()->with('error', 'El motivo de deshabilitación es obligatorio.');
        }

        // Clean idempotency check
        if ((int) ($auth['agency']['activo'] ?? 0) === 0) {
            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status'  => 'error',
                    'message' => 'La agencia ya se encuentra deshabilitada.',
                ]);
            }

            return redirect()->to(site_url('agencias/ver/' . $id))
                ->with('warning', 'La agencia ya se encuentra deshabilitada.');
        }

        $idPais         = (int) session('id_pais');
        $codigoEmpleado = (string) session('codigo_empleado');

        try {
            $this->agenciaModel->softDisableWithAudit((int) $id, $idPais, $motivo, $codigoEmpleado);

            if ($this->isJsonRequest()) {
                return $this->response->setStatusCode(200)->setJSON([
                    'status'  => 'ok',
                    'message' => 'Agencia deshabilitada exitosamente.',
                ]);
            }

            return redirect()->to(site_url('agencias/ver/' . $id))
                ->with('success', 'Agencia deshabilitada exitosamente.');
        } catch (DomainException $e) {
            return redirect()->to(site_url('agencias/ver/' . $id))->with('warning', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', '[AgenciaController::deshabilitar] ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al deshabilitar la agencia.');
        }
    }

    /**
     * GET agencias/mapa
     */
    public function mapa()
    {
        $idPais     = (int) session('id_pais');
        $paisNombre = $this->agencyAccess->getSupervisorCountryName();

        $agencias      = $this->agenciaModel->findForGeolocation($idPais);
        $departamentos = $this->departamentoModel->findByPais($idPais);
        $marcas        = $this->marcaModel->findAllOrdered();

        $data = [
            'idPais'        => $idPais,
            'paisNombre'    => $paisNombre,
            'agencias'      => $agencias,
            'departamentos' => $departamentos,
            'marcas'        => $marcas,
        ];

        return view('agencias/mapa', $data);
    }
}
