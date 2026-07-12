"""
Generates model_comparison_notebook.ipynb - a real, runnable Jupyter
notebook (nbformat v4 JSON, written directly so this script has no jupyter/
nbformat dependency itself; open the resulting .ipynb with VS Code's
built-in notebook support or `pip install jupyter && jupyter notebook`)
that loads the actual JSON reports produced by model_comparison.py,
evaluate.py, explain.py, train_multioutput.py, and bias_fairness_report.py,
and renders them as comparison tables and charts - a single deliverable
tying together every number reported in ML_RESEARCH_METHODOLOGY.md.

Run: python generate_notebook.py
Output: model_comparison_notebook.ipynb
"""
import json
from pathlib import Path

OUT = Path(__file__).parent / "model_comparison_notebook.ipynb"


def md(text: str) -> dict:
    return {"cell_type": "markdown", "metadata": {}, "source": text.splitlines(keepends=True)}


def code(text: str) -> dict:
    return {"cell_type": "code", "execution_count": None, "metadata": {}, "outputs": [], "source": text.splitlines(keepends=True)}


CELLS = [
    md("# MindRise Exam-Readiness Model: Comparison & Evaluation Notebook\n\n"
       "Loads the real JSON reports produced by the research-grade ML pipeline "
       "(`model_comparison.py`, `evaluate.py`, `explain.py`, `train_multioutput.py`, "
       "`bias_fairness_report.py`) and renders them as tables/charts. Every number "
       "here traces to a file under `models/` - nothing in this notebook is computed "
       "independently of the training scripts, so re-running training and re-running "
       "this notebook always stay consistent.\n\n"
       "See `docs/ML_RESEARCH_METHODOLOGY.md` for the full narrative methodology this "
       "notebook's numbers back."),
    code("import json\n"
         "from pathlib import Path\n\n"
         "import pandas as pd\n"
         "import matplotlib.pyplot as plt\n\n"
         "MODELS = Path('models')\n\n"
         "def load(name):\n"
         "    path = MODELS / name\n"
         "    if not path.exists():\n"
         "        print(f'{name} not found yet - run the corresponding training script first.')\n"
         "        return None\n"
         "    return json.loads(path.read_text())\n\n"
         "comparison = load('model_comparison_report.json') or load('metadata.json')\n"
         "evaluation = load('evaluation_report.json')\n"
         "explainability = load('explainability_report.json')\n"
         "multioutput = load('multioutput_metadata.json')\n"
         "bias = load('bias_fairness_report.json')\n"
         "registry = load('registry.json')"),

    md("## 1. Dataset composition"),
    code("if comparison:\n"
         "    print('Training rows:', comparison.get('training_rows'))\n"
         "    counts = comparison.get('data_source_counts', {})\n"
         "    if counts:\n"
         "        pd.Series(counts).plot(kind='bar', title='Hybrid dataset composition', ylabel='rows')\n"
         "        plt.tight_layout()\n"
         "        plt.show()"),

    md("## 2. Model screening (9 candidates, 5-fold CV)"),
    code("if comparison and 'screening_round' in comparison:\n"
         "    screening_df = pd.DataFrame({\n"
         "        name: {'cv_f1_macro_mean': r['cv_f1_macro_mean'], 'cv_f1_macro_std': r['cv_f1_macro_std'], 'elapsed_seconds': r['elapsed_seconds']}\n"
         "        for name, r in comparison['screening_round'].items()\n"
         "    }).T.sort_values('cv_f1_macro_mean', ascending=False)\n"
         "    display(screening_df)\n"
         "    screening_df['cv_f1_macro_mean'].plot(kind='barh', xerr=screening_df['cv_f1_macro_std'], title='Screening macro-F1 by model (5-fold CV)')\n"
         "    plt.tight_layout()\n"
         "    plt.show()\n"
         "else:\n"
         "    print('Run model_comparison.py to populate this section.')"),

    md("## 3. Hyperparameter optimization: default vs. optimized"),
    code("if comparison and 'default_vs_optimized_test_f1' in comparison:\n"
         "    hpo_df = pd.DataFrame(comparison['default_vs_optimized_test_f1']).T\n"
         "    hpo_df['improvement'] = hpo_df['optimized_test_f1_macro'] - hpo_df['default_test_f1_macro']\n"
         "    display(hpo_df)\n"
         "    print(f\"\\nSelected model: {comparison.get('best_model')}\")\n"
         "    print(f\"\\nTabNet exclusion rationale:\\n{comparison.get('tabnet_exclusion_rationale', '(see ML_RESEARCH_METHODOLOGY.md Sec 5.2)')}\")"),

    md("## 4. Comprehensive evaluation"),
    code("if evaluation:\n"
         "    display(pd.Series(evaluation['core_metrics'], name='value').to_frame())\n"
         "    print('\\nOverfitting diagnosis:', evaluation['overfitting_diagnosis']['diagnosis'])\n"
         "    print('10-fold CV macro-F1:', evaluation['cross_validation']['ten_fold_cv']['mean'])\n"
         "    print('Repeated 5x3 CV macro-F1:', evaluation['cross_validation']['repeated_cv_5x3']['mean'])\n"
         "    ci = evaluation['bootstrap_confidence_intervals']['f1_macro']\n"
         "    print(f\"Bootstrap 95% CI on macro-F1: [{ci['ci_lower']:.4f}, {ci['ci_upper']:.4f}]\")\n"
         "else:\n"
         "    print('Run evaluate.py to populate this section.')"),

    code("if evaluation:\n"
         "    ts, tr, va = evaluation['overfitting_diagnosis']['train_sizes'], evaluation['overfitting_diagnosis']['train_scores_mean'], evaluation['overfitting_diagnosis']['val_scores_mean']\n"
         "    plt.plot(ts, tr, label='train')\n"
         "    plt.plot(ts, va, label='cross-validation')\n"
         "    plt.xlabel('training set size'); plt.ylabel('macro-F1'); plt.title('Learning curve'); plt.legend()\n"
         "    plt.show()"),

    md("## 5. Per-data-source performance (real vs. synthetic generalization check)"),
    code("if evaluation and evaluation.get('per_data_source_performance'):\n"
         "    display(pd.DataFrame(evaluation['per_data_source_performance']).T)"),

    md("## 6. Explainability: global SHAP importance"),
    code("if explainability:\n"
         "    imp_df = pd.DataFrame(explainability['global_shap_importance'], columns=['feature', 'mean_abs_shap']).set_index('feature')\n"
         "    display(imp_df.head(15))\n"
         "    imp_df.head(15).sort_values('mean_abs_shap').plot(kind='barh', title='Top 15 features by mean |SHAP|', legend=False)\n"
         "    plt.tight_layout()\n"
         "    plt.show()\n"
         "    lime = explainability.get('lime_cross_check', {})\n"
         "    print('LIME/SHAP mean top-5 feature overlap:', lime.get('mean_top5_feature_overlap_with_shap'))\n"
         "else:\n"
         "    print('Run explain.py to populate this section.')"),

    md("## 7. Multi-output models (real OULAD ground truth)"),
    code("if multioutput:\n"
         "    display(pd.DataFrame(multioutput['targets']).T)\n"
         "    print('\\nExcluded outputs and why:')\n"
         "    for name, reason in multioutput.get('excluded_outputs', {}).items():\n"
         "        print(f'  {name}: {reason}')\n"
         "else:\n"
         "    print('Run train_multioutput.py to populate this section.')"),

    md("## 8. Bias / fairness analysis (real OULAD demographics)"),
    code("if bias:\n"
         "    for group, values in bias['outcome_distribution'].items():\n"
         "        print(f'\\n{group}:')\n"
         "        for k, v in values.items():\n"
         "            print(f\"  {k}: n={v['n']}, ready_share={v['label_distribution'].get('ready', 0):.3f}\")\n"
         "else:\n"
         "    print('Run data_pipeline/bias_fairness_report.py to populate this section.')"),

    md("## 9. Model version registry"),
    code("if registry and registry.get('versions'):\n"
         "    display(pd.DataFrame(registry['versions']))\n"
         "    print('\\nLive version:', registry.get('live_version'))\n"
         "else:\n"
         "    print('No versions registered yet - run model_registry.py or retrain.py.')"),
]

notebook = {
    "cells": CELLS,
    "metadata": {
        "kernelspec": {"display_name": "Python 3", "language": "python", "name": "python3"},
        "language_info": {"name": "python", "version": "3.11"},
    },
    "nbformat": 4,
    "nbformat_minor": 5,
}

OUT.write_text(json.dumps(notebook, indent=1, ensure_ascii=False))
print(f"Wrote {OUT}")
