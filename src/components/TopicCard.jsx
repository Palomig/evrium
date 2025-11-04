import { motion } from 'framer-motion';

const TopicCard = ({ title, description, children, className = '' }) => {
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className={`card ${className}`}
    >
      {title && (
        <h3 className="text-xl md:text-2xl font-bold text-gray-800 mb-3">
          {title}
        </h3>
      )}
      {description && (
        <p className="text-gray-600 mb-4 text-sm md:text-base">
          {description}
        </p>
      )}
      <div className="mt-4">{children}</div>
    </motion.div>
  );
};

export default TopicCard;
