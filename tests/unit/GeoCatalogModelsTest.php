<?php

namespace Tests\Unit;

use App\Database\Seeds\AuthSeeder;
use App\Models\AgenciaModel;
use App\Models\AuditoriaAgenciaModel;
use App\Models\DepartamentoModel;
use App\Models\DistritoModel;
use App\Models\MarcaModel;
use App\Models\MunicipioModel;
use App\Models\PaisModel;
use App\Models\VisitaModel;
use CodeIgniter\Database\Postgre\Connection as PostgreConnection;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Unit tests for PaisModel, MarcaModel, DepartamentoModel, MunicipioModel,
 * DistritoModel, and DualDriverTrait PostgreSQL identifier switching & normalization.
 *
 * @internal
 */
final class GeoCatalogModelsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = AuthSeeder::class;

    public function testPaisModelMethodsAndDistrictRequirement(): void
    {
        $paisModel = new PaisModel();

        $sv = $paisModel->findById(1);
        $this->assertNotNull($sv);
        $this->assertSame(1, $sv['Id_pais']);
        $this->assertSame(1, $sv['id_pais']);
        $this->assertSame('El Salvador', $sv['Pais']);
        $this->assertSame('El Salvador', $sv['pais']);
        $this->assertSame(1, $sv['requiere_distrito']);

        $this->assertTrue($paisModel->requiresDistrict(1));
        $this->assertFalse($paisModel->requiresDistrict(2)); // Guatemala
        $this->assertTrue($paisModel->requiresDistrict(3));  // Honduras
        $this->assertFalse($paisModel->requiresDistrict(999));
        $this->assertNull($paisModel->findById(0));

        $all = $paisModel->findAllOrdered();
        $this->assertCount(3, $all);
        $this->assertSame('El Salvador', $all[0]['Pais']);
        $this->assertSame('Guatemala', $all[1]['Pais']);
        $this->assertSame('Honduras', $all[2]['Pais']);
    }

    public function testMarcaModelMethods(): void
    {
        $marcaModel = new MarcaModel();

        $all = $marcaModel->findAllOrdered();
        $this->assertGreaterThanOrEqual(7, count($all));
        $names = array_column($all, 'Marca');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);

        $byIds = $marcaModel->findByIds([2, '1', 2, -5, 0, 9999]);
        $this->assertCount(2, $byIds);
        $this->assertSame([1, 2], array_column($byIds, 'id_marca'));
        $this->assertSame([], $marcaModel->findByIds([0, -1]));

        $honda = $marcaModel->findByName('  hOnDa ');
        $this->assertNotNull($honda);
        $this->assertSame(1, $honda['Id_marca']);
        $this->assertNull($marcaModel->findByName('   '));
        $this->assertNull($marcaModel->findByName('MarcaInexistente'));
    }

    public function testCascadingGeoHierarchyModels(): void
    {
        $deptoModel = new DepartamentoModel();
        $muniModel  = new MunicipioModel();
        $distModel  = new DistritoModel();

        $svDeptos = $deptoModel->findByPais(1);
        $this->assertNotEmpty($svDeptos);
        $this->assertTrue($deptoModel->belongsToPais(1, 1));
        $this->assertFalse($deptoModel->belongsToPais(5, 1)); // Guatemala dept in SV
        $this->assertSame([], $deptoModel->findByPais(0));

        $ssMunis = $muniModel->findByDepartamento(1);
        $this->assertNotEmpty($ssMunis);
        $this->assertTrue($muniModel->belongsToDepartamento(1, 1));
        $this->assertFalse($muniModel->belongsToDepartamento(7, 1)); // Guatemala muni in SV dept
        $this->assertSame([], $muniModel->findByDepartamento(-1));

        $ssDistritos = $distModel->findByMunicipio(1);
        $this->assertNotEmpty($ssDistritos);
        $this->assertTrue($distModel->belongsToMunicipio(1, 1));
        $this->assertFalse($distModel->belongsToMunicipio(5, 1)); // Santa Tecla district in SS Centro muni
        $this->assertSame([], $distModel->findByMunicipio(0));

        // Guatemala municipality has no districts
        $this->assertSame([], $distModel->findByMunicipio(7));
    }

    public function testDualDriverPostgreIdentifierSwitchingAndRowNormalization(): void
    {
        $pgDb = new PostgreConnection([]);

        $paisModel      = new PaisModel($pgDb);
        $marcaModel     = new MarcaModel($pgDb);
        $deptoModel     = new DepartamentoModel($pgDb);
        $muniModel      = new MunicipioModel($pgDb);
        $distModel      = new DistritoModel($pgDb);
        $agenciaModel   = new AgenciaModel($pgDb);
        $auditoriaModel = new AuditoriaAgenciaModel($pgDb);
        $visitaModel    = new VisitaModel($pgDb);

        $this->assertSame('pais', $paisModel->getTable());
        $this->assertSame('id_pais', $paisModel->primaryKey);
        $this->assertSame('marca', $marcaModel->getTable());
        $this->assertSame('id_marca', $marcaModel->primaryKey);
        $this->assertSame('departamento', $deptoModel->getTable());
        $this->assertSame('id_departamento', $deptoModel->primaryKey);
        $this->assertSame('municipio', $muniModel->getTable());
        $this->assertSame('id_municipio', $muniModel->primaryKey);
        $this->assertSame('distrito', $distModel->getTable());
        $this->assertSame('id_distrito', $distModel->primaryKey);
        $this->assertSame('agencias', $agenciaModel->getTable());
        $this->assertSame('idagencia', $agenciaModel->primaryKey);
        $this->assertSame('auditoria_agencias', $auditoriaModel->getTable());
        $this->assertSame('id_auditoria', $auditoriaModel->primaryKey);
        $this->assertSame('visitas', $visitaModel->getTable());
        $this->assertSame('id_visita', $visitaModel->primaryKey);

        // Verify normalizeAfterFind populates mixed-case and lowercase keys from a PostgreSQL lowercase row
        $refMethod = new \ReflectionMethod($agenciaModel, 'normalizeAfterFind');
        $refMethod->setAccessible(true);
        $event = $refMethod->invoke($agenciaModel, [
            'singleton' => true,
            'data'      => [
                'idagencia'       => '12',
                'nombre_agencia'  => 'Agencia PG',
                'id_pais'         => '2',
                'id_departamento' => '5',
                'id_municipio'    => '7',
                'id_distrito'     => null,
                'latitud'         => '14.63491500',
                'longitud'        => '-90.50688200',
                'id_marca'        => '1',
                'activo'          => '1',
            ],
        ]);

        $row = $event['data'];
        $this->assertSame(12, $row['Idagencia']);
        $this->assertSame(12, $row['idagencia']);
        $this->assertSame(2, $row['Id_pais']);
        $this->assertNull($row['Id_distrito']);
        $this->assertNull($row['id_distrito']);
        $this->assertEqualsWithDelta(14.634915, $row['latitud'], 0.000001);
        $this->assertSame(1, $row['activo']);

        // Verify mapBeforeWrite maps mixed-case input keys to lowercase for PostgreSQL writes
        $writeMethod = new \ReflectionMethod($agenciaModel, 'mapBeforeWrite');
        $writeMethod->setAccessible(true);
        $writeEvent = $writeMethod->invoke($agenciaModel, [
            'data' => [
                'nombre_agencia'  => 'Agencia PG',
                'Id_pais'         => 2,
                'Id_departamento' => 5,
                'Id_municipio'    => 7,
                'Id_distrito'     => null,
                'Id_marca'        => 1,
            ],
        ]);
        $this->assertArrayHasKey('id_pais', $writeEvent['data']);
        $this->assertArrayNotHasKey('Id_pais', $writeEvent['data']);
        $this->assertArrayHasKey('id_departamento', $writeEvent['data']);
        $this->assertArrayHasKey('id_municipio', $writeEvent['data']);
        $this->assertArrayHasKey('id_distrito', $writeEvent['data']);
        $this->assertArrayHasKey('id_marca', $writeEvent['data']);
    }
}
