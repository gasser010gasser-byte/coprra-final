# AI Components Map - COPRRA System

## Overview
This document provides a comprehensive mapping of all AI components, their dependencies, data flows, and integrations within the COPRRA Laravel application.

## Core AI Services Architecture

### 1. Primary AI Services

#### AIService (`app/Services/AI/AIService.php`)
- **Purpose**: Main orchestrator for AI operations
- **Dependencies**: 
  - `AITextAnalysisService`
  - `AIImageAnalysisService`
- **Methods**:
  - `analyzeText(string $text): array`
  - `classifyProduct(string $description): string`
  - `generateRecommendations(array $context): array`
  - `analyzeImage(string $imageUrl, string $prompt): array`

#### AIRequestService (`app/Services/AI/AIRequestService.php`)
- **Purpose**: HTTP client for external AI API calls
- **Dependencies**: None (base service)
- **Key Features**:
  - Request/response logging
  - Error handling and retries
  - Testing mode with mock responses
  - Configurable timeouts and headers
- **Methods**:
  - `makeRequest(string $endpoint, array $data): array`

#### AITextAnalysisService (`app/Services/AI/AITextAnalysisService.php`)
- **Purpose**: Text analysis using GPT-4
- **Dependencies**: `AIRequestService`
- **AI Model**: `gpt-4`
- **Methods**:
  - `analyzeText(string $text): array` - Sentiment, keywords, summary
  - `classifyProduct(string $description): string` - Product categorization
  - `generateRecommendations(array $context): array` - Product recommendations

#### AIImageAnalysisService (`app/Services/AI/AIImageAnalysisService.php`)
- **Purpose**: Image analysis using GPT-4 Vision
- **Dependencies**: `AIRequestService`
- **AI Model**: `gpt-4-vision-preview`
- **Methods**:
  - `analyzeImage(string $imageUrl, string $prompt): array` - Image content analysis

### 2. AI Monitoring & Quality Agents

#### StrictQualityAgent (`app/Services/AI/StrictQualityAgent.php`)
- **Purpose**: Enforces strict quality standards for AI responses
- **Dependencies**: 
  - `HealthScoreCalculator`
  - `RuleExecutorService`
- **Execution**: Scheduled via `app/Console/Kernel.php`
- **Configuration**: Controlled by `AI_STRICT_AGENT_ENABLED`

#### ContinuousQualityMonitor (`app/Services/AI/ContinuousQualityMonitor.php`)
- **Purpose**: Continuous monitoring of AI service quality
- **Dependencies**: `HealthScoreCalculator`
- **Execution**: Scheduled via `app/Console/Kernel.php`
- **Configuration**: Controlled by `AI_MONITOR_ENABLED`

#### HealthScoreCalculator (`app/Services/AI/HealthScoreCalculator.php`)
- **Purpose**: Calculates health scores for AI services
- **Dependencies**: None
- **Used by**: `StrictQualityAgent`, `ContinuousQualityMonitor`

### 3. Supporting AI Services

#### AlertManagerService (`app/Services/AI/AlertManagerService.php`)
- **Purpose**: Manages AI-related alerts and notifications
- **Methods**: `createDefaultAlert(): array`

#### HealthScoreService (`app/Services/AI/HealthScoreService.php`)
- **Purpose**: Health score management and calculation
- **Methods**: `getDefaultScore(): int`

#### RuleExecutorService (`app/Services/AI/RuleExecutorService.php`)
- **Purpose**: Executes monitoring rules and policies
- **Methods**: `execute(): bool`

#### RuleValidatorService (`app/Services/AI/RuleValidatorService.php`)
- **Purpose**: Validates rule configurations and health metrics
- **Methods**:
  - `validateRuleName(string $name): bool`
  - `validateThreshold(float $threshold): bool`
  - `validateCommand(string $command): bool`
  - `validateHealthScore(int $score): bool`
  - `validateLastCheck(string $timestamp): bool`

## API Endpoints & Controllers

### Admin AI Control Panel
**Controller**: `app/Http/Controllers/Admin/AIControlPanelController.php`
**Routes** (defined in `routes/web.php`):
- `GET /admin/ai/` - AI dashboard
- `POST /admin/ai/analyze-text` - Text analysis
- `POST /admin/ai/classify-product` - Product classification
- `POST /admin/ai/recommendations` - Generate recommendations
- `POST /admin/ai/analyze-image` - Image analysis
- `GET /admin/ai/status` - AI service status

### Public AI API
**Controller**: `app/Http/Controllers/AIController.php` (currently empty)
**Routes** (defined in `routes/api.php`):
- `POST /ai/analyze` - General analysis endpoint
- `POST /ai/classify-product` - Product classification
- `POST /ai/analyze-text` - Text analysis
- `POST /ai/classify-product` - Product classification (duplicate)
- `POST /ai/analyze-image` - Image analysis
- `POST /ai/generate-recommendations` - Recommendations

## Configuration & Environment

### AI Configuration (`config/ai.php`)
```php
'api_key' => env('AI_API_KEY', env('OPENAI_API_KEY', ''))
'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1')
'timeout' => env('AI_TIMEOUT', 30)
'max_tokens' => env('AI_MAX_TOKENS', 2000)
'temperature' => env('AI_TEMPERATURE', 0.5)
'disable_external_calls' => env('AI_DISABLE_EXTERNAL_CALLS', 'testing' === env('APP_ENV'))
```

### Models Configuration
- **Text Model**: `gpt-3.5-turbo` (configurable via `AI_TEXT_MODEL`)
- **Image Model**: `gpt-4-vision-preview` (configurable via `AI_IMAGE_MODEL`)
- **Embedding Model**: `text-embedding-ada-002` (configurable via `AI_EMBEDDING_MODEL`)

### Cache Configuration
- **Enabled**: `AI_CACHE_ENABLED` (default: true)
- **TTL**: `AI_CACHE_TTL` (default: 3600 seconds)
- **Prefix**: `AI_CACHE_PREFIX` (default: 'ai_')

### Rate Limiting
- **Enabled**: `AI_RATE_LIMIT_ENABLED` (default: true)
- **Max Requests**: `AI_RATE_LIMIT_MAX` (default: 100)
- **Per Minutes**: `AI_RATE_LIMIT_MINUTES` (default: 60)

## Data Flow Diagrams

### Text Analysis Flow
```
User Request → AIController/AIControlPanelController 
    → AIService.analyzeText() 
    → AITextAnalysisService.analyzeText() 
    → AIRequestService.makeRequest() 
    → OpenAI GPT-4 API 
    → Response Processing 
    → JSON Response
```

### Image Analysis Flow
```
User Request → AIController/AIControlPanelController 
    → AIService.analyzeImage() 
    → AIImageAnalysisService.analyzeImage() 
    → AIRequestService.makeRequest() 
    → OpenAI GPT-4 Vision API 
    → Response Processing 
    → JSON Response
```

### Quality Monitoring Flow
```
Scheduled Task (Kernel.php) 
    → ContinuousQualityMonitor.execute() 
    → HealthScoreCalculator.calculate() 
    → AlertManagerService (if issues detected)
```

## Prompt Templates & AI Integration

### Text Analysis Prompts
**System Prompt**: "You are an AI assistant that analyzes text content. Provide structured analysis including sentiment, keywords, and summary."

**User Prompt Template**: "Analyze the following text: {text}"

### Product Classification Prompts
**System Prompt**: "You are a product classification expert. Classify products into appropriate categories based on their descriptions."

**User Prompt Template**: "Classify this product: {description}"

### Image Analysis Prompts
**User Prompt Template**: "Analyze this image and provide: {prompt}"

## Testing Infrastructure

### Test Coverage
- **Unit Tests**: `tests/AI/` directory
- **Mock Services**: `MockAIService` for testing
- **Test Categories**:
  - Unit tests for all AI services
  - Edge case testing
  - Error handling tests
  - Response time tests
  - Model performance tests
  - Accuracy tests

### Testing Configuration
- **External Calls Disabled**: Automatic in testing environment
- **Mock Responses**: Generated based on input patterns
- **Test Data**: Predefined scenarios for consistent testing

## Service Dependencies Map

```
AIService
├── AITextAnalysisService
│   └── AIRequestService
└── AIImageAnalysisService
    └── AIRequestService

StrictQualityAgent
├── HealthScoreCalculator
└── RuleExecutorService

ContinuousQualityMonitor
└── HealthScoreCalculator

Quality Support Services
├── AlertManagerService
├── HealthScoreService
└── RuleValidatorService
```

## Service Provider Registrations

### Current State
- **No dedicated AI Service Provider**: AI services are not explicitly registered in service providers
- **Manual Instantiation**: Services are instantiated directly in controllers and scheduled tasks
- **Missing Dependency Injection**: AI services are not bound in the Laravel service container

### Recommended Improvements
1. Create `AIServiceProvider` for proper dependency injection
2. Register AI services as singletons in service container
3. Implement proper interface contracts for AI services
4. Add configuration-based service binding

## External Integrations

### OpenAI API Integration
- **Endpoint**: `https://api.openai.com/v1`
- **Authentication**: API key via `AI_API_KEY` or `OPENAI_API_KEY`
- **Models Used**:
  - GPT-4 for text analysis
  - GPT-4 Vision for image analysis
- **Request Handling**: Via `AIRequestService`

### No Other AI Providers
- Currently only integrated with OpenAI
- No Anthropic, Claude, or other AI service integrations found

## Identified Issues & Recommendations

### 1. Missing Service Provider Registration
**Issue**: AI services are not registered in Laravel's service container
**Impact**: Manual instantiation, no dependency injection benefits
**Recommendation**: Create `AIServiceProvider` with proper bindings

### 2. Inconsistent Naming
**Issue**: Some services have inconsistent naming patterns
**Impact**: Confusion in codebase navigation
**Recommendation**: Standardize naming conventions

### 3. Empty AIController
**Issue**: `AIController.php` is empty despite having defined routes
**Impact**: API endpoints are not functional
**Recommendation**: Implement controller methods

### 4. Duplicate Routes
**Issue**: Some API routes are duplicated in `routes/api.php`
**Impact**: Potential routing conflicts
**Recommendation**: Remove duplicate route definitions

### 5. Missing Documentation
**Issue**: Limited inline documentation for AI services
**Impact**: Reduced maintainability
**Recommendation**: Add comprehensive PHPDoc comments

## Security Considerations

### API Key Management
- API keys stored in environment variables
- No hardcoded secrets in codebase
- Proper fallback mechanism for different key names

### Request Validation
- Input validation in controllers
- Sanitization of user inputs before AI processing
- Error handling to prevent information leakage

### Rate Limiting
- Configurable rate limiting for AI endpoints
- Protection against API abuse
- Monitoring and alerting capabilities

## Performance Optimizations

### Caching Strategy
- Response caching with configurable TTL
- Cache key prefixing for organization
- Environment-based cache control

### Request Optimization
- Configurable timeouts
- Retry mechanisms
- Efficient request batching potential

### Monitoring & Alerting
- Health score calculation
- Continuous quality monitoring
- Automated alert generation

## Future Enhancement Opportunities

1. **Multi-Provider Support**: Add support for Anthropic, Claude, etc.
2. **Advanced Caching**: Implement intelligent cache invalidation
3. **Batch Processing**: Add support for bulk AI operations
4. **Real-time Monitoring**: Enhanced monitoring dashboard
5. **A/B Testing**: Framework for testing different AI models
6. **Cost Optimization**: Usage tracking and cost management
7. **Custom Models**: Support for fine-tuned models
8. **Webhook Integration**: Real-time notifications for AI events

---

*Generated on: $(date)*
*Last Updated: $(date)*