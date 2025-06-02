<?php 

// Define o namespace onde o componente Livewire está localizado.
// Isso ajuda a organizar melhor o código dentro do projeto.
namespace App\Livewire\Planning\SearchStrategy;

// Importa a classe base para componentes Livewire.
use Livewire\Component;

// Importa o model de Projeto e renomeia como ProjectModel para evitar conflito com outros nomes.
use App\Models\Project as ProjectModel;

// Importa o model de Estratégia de Busca e renomeia como SearchStrategyModel.
use App\Models\SearchStrategy as SearchStrategyModel;

// Importa o helper para registrar logs de atividades do usuário.
use App\Utils\ActivityLogHelper as Log;

// Importa o helper responsável por exibir mensagens de notificação (toasts).
use App\Utils\ToastHelper;

// Importa um trait com métodos para checar permissões relacionadas ao projeto.
use App\Traits\ProjectPermissions;

/**
 * Componente Livewire responsável por gerenciar a **estratégia de busca** (search strategy)
 * associada a um projeto. Ele permite exibir, editar e salvar uma descrição textual
 * da estratégia adotada para conduzir a busca no projeto.
 */
class Strategy extends Component
{
    // Usa o trait que fornece funções de verificação de permissões no projeto
    use ProjectPermissions;

    // Armazena o ID do projeto atual
    public $projectId;

    // Armazena o objeto do projeto atual, recuperado do banco de dados
    public $currentProject;

    // Armazena o modelo da estratégia de busca associada ao projeto
    public $searchStrategy;

    // Armazena a descrição atual da estratégia, que pode ser editada pelo usuário
    public $currentDescription;

    // Caminho para os arquivos de mensagens de toast (notificações)
    private $toastMessages = 'project/planning.search-strategy';

    // Regras de validação para o campo de descrição
    protected $rules = [
        'currentDescription' => [
            'required', // O campo é obrigatório
            'string',   // Deve ser uma string
        ],
    ];
    
    // Mensagens personalizadas de erro para validação
    protected $messages = [
        'currentDescription.required' => 'O campo descrição é obrigatório.',
        'currentDescription.regex' => 'A descrição deve conter pelo menos uma letra e não pode conter apenas caracteres especiais ou números.',
    ];

    /**
     * Método executado automaticamente quando o componente é carregado.
     * Ele inicializa os dados com base no ID do projeto presente na URL.
     */
    public function mount()
    {
        // Captura o segundo segmento da URL como o ID do projeto
        $projectId = request()->segment(2);

        // Armazena o ID do projeto
        $this->projectId = $projectId;

        // Busca o projeto correspondente no banco de dados, ou lança erro se não encontrar
        $this->currentProject = ProjectModel::findOrFail($this->projectId);

        // Busca a estratégia de busca existente ou cria uma nova instância se não houver
        $this->searchStrategy = SearchStrategyModel::where('id_project', $this->projectId)->firstOrNew([]);

        // Preenche a descrição atual da estratégia, se houver
        $this->currentDescription = $this->searchStrategy->description;
    }

    /**
     * Exibe uma mensagem de notificação (toast) na interface do usuário.
     *
     * @param string $message A mensagem a ser exibida.
     * @param string $type O tipo da mensagem (ex: 'success', 'error').
     */
    public function toast(string $message, string $type)
    {
        // Dispara o evento 'search-strategy' com a mensagem formatada pelo helper
        $this->dispatch('search-strategy', ToastHelper::dispatch($type, $message));
    }

    /**
     * Valida e salva a descrição da estratégia de busca no banco de dados.
     * Também registra a atividade no log e exibe uma notificação ao usuário.
     */
    public function submit()
    {
        // Valida os dados conforme as regras básicas definidas
        $this->validate([
            'currentDescription' => 'required|string',
        ]);

        // Validação adicional: a descrição deve conter ao menos uma letra
        if (!$this->isValidDescription($this->currentDescription)) {
            // Adiciona erro personalizado
            $this->addError('currentDescription', 'A descrição deve conter pelo menos uma letra e não pode conter apenas caracteres especiais ou números.');
            return;
        }

        try {
            // Garante que o projeto ainda existe no banco de dados
            $project = ProjectModel::findOrFail($this->projectId);

            // Atualiza ou cria a estratégia de busca relacionada ao projeto
            $project->searchStrategy()->updateOrCreate([], [
                'description' => $this->currentDescription
            ]);

            // Registra a atividade no log do sistema
            Log::logActivity(
                action: 'Updated the search strategy',
                description: $this->currentDescription,
                projectId: $this->projectId
            );

            // Exibe notificação de sucesso
            $this->toast(
                message: __('project/planning.search-strategy.success'),
                type: 'success'
            );
        } catch (\Exception $e) {
            // Em caso de erro, exibe a mensagem de erro ao usuário
            $this->toast(
                message: $e->getMessage(),
                type: 'error'
            );
        }
    }

    /**
     * Valida se a descrição informada é válida.
     * Deve conter ao menos uma letra e não pode conter apenas números ou símbolos.
     *
     * @param string $description Descrição fornecida pelo usuário.
     * @return bool true se válida, false se inválida.
     */
    private function isValidDescription(string $description): bool
    {
        // Remove espaços em branco do início e fim
        $trimmedDescription = trim($description);

        // Verifica se contém ao menos uma letra (inclusive letras acentuadas)
        if (!preg_match('/[a-zA-ZÀ-ÿ]/', $trimmedDescription)) {
            return false;
        }

        // Verifica se a string é composta apenas por números e/ou símbolos
        if (preg_match('/^[\d\W]+$/', $trimmedDescription)) {
            return false;
        }

        return true;
    }

    /**
     * Renderiza a view Livewire correspondente a este componente.
     *
     * @return \Illuminate\View\View A view que será exibida.
     */
    public function render()
    {
        // Retorna a view específica do componente de estratégia de busca
        return view('livewire.planning.search-strategy.strategy');
    }
}
