import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { motion } from 'framer-motion';

const Quiz = ({ question, correctAnswer, hint, onCorrect }) => {
  const [showAnswer, setShowAnswer] = useState(false);
  const [isCorrect, setIsCorrect] = useState(null);
  const { register, handleSubmit, reset } = useForm();

  const onSubmit = (data) => {
    const userAnswer = parseFloat(data.answer);
    const correct = Math.abs(userAnswer - correctAnswer) < 0.01;
    setIsCorrect(correct);
    setShowAnswer(true);

    if (correct && onCorrect) {
      onCorrect();
    }
  };

  const resetQuiz = () => {
    setShowAnswer(false);
    setIsCorrect(null);
    reset();
  };

  return (
    <div className="card bg-blue-50">
      <h4 className="font-semibold text-gray-800 mb-3 text-sm md:text-base">
        {question}
      </h4>

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-3">
        <div className="flex flex-col sm:flex-row gap-2">
          <input
            type="number"
            step="0.01"
            {...register('answer', { required: true })}
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm md:text-base"
            placeholder="Введите ответ..."
            disabled={showAnswer}
          />
          <button
            type="submit"
            disabled={showAnswer}
            className="btn-primary whitespace-nowrap text-sm md:text-base"
          >
            Проверить
          </button>
        </div>
      </form>

      {hint && !showAnswer && (
        <p className="text-xs md:text-sm text-gray-600 mt-2">
          💡 Подсказка: {hint}
        </p>
      )}

      {showAnswer && (
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className={`mt-4 p-3 md:p-4 rounded-lg ${
            isCorrect ? 'bg-green-100' : 'bg-red-100'
          }`}
        >
          <p
            className={`font-semibold text-sm md:text-base ${
              isCorrect ? 'text-green-800' : 'text-red-800'
            }`}
          >
            {isCorrect ? '✅ Правильно!' : '❌ Неверно'}
          </p>
          <p className="text-xs md:text-sm mt-2">
            Правильный ответ: {correctAnswer}
          </p>
          <button
            onClick={resetQuiz}
            className="mt-3 text-xs md:text-sm text-blue-600 hover:underline"
          >
            Попробовать снова
          </button>
        </motion.div>
      )}
    </div>
  );
};

export default Quiz;
